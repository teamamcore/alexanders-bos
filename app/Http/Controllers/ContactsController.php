<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Reminder;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Contacts module (design notes §3, contacts.jpeg): business = parent;
 * locations each paired with a local contact = children; a job's contact card
 * is the child filtered to that job's threads. Paid $ YTD + AR mini chart.
 */
final class ContactsController extends Controller
{
    public function index(Request $request): View
    {
        $q = (string) $request->query('q', '');
        $customers = Customer::query()->with(['owner', 'corpPoc', 'serviceLocations'])->withCount(['jobs', 'serviceLocations'])
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->get();

        return view('ops.contacts.index', [
            'q' => $q,
            'customers' => $customers->map(fn (Customer $c) => ['c' => $c, 'ytd' => $c->paidYtd()]),
            'segments' => config('batsons.customer_segments', []),
            'owners' => User::query()->where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $customer->load(['owner', 'corpPoc', 'contacts', 'serviceLocations.contact', 'jobs' => fn ($q) => $q->latest()->take(20)]);

        $locationId = (int) $request->query('location', 0);
        $jobId = (int) $request->query('job', 0);
        $threads = CustomerMessage::query()->where('customer_id', $customer->id)->with(['job', 'workOrder', 'sender'])
            ->when($jobId, fn ($q) => $q->where('job_id', $jobId))
            ->when($locationId && ! $jobId, fn ($q) => $q->whereIn('job_id', Job::query()->where('service_location_id', $locationId)->pluck('id')))
            ->latest()->take(40)->get();

        // AR / revenue mini chart: paid $ by month, last 12 months
        $series = [];
        $start = today()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $series[] = ['label' => $m->format('M'), 'paid' => (float) Invoice::query()->where('customer_id', $customer->id)->where('status', 'paid')->whereBetween('paid_at', [$m, $m->copy()->endOfMonth()])->sum('total'),
                'open' => (float) Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['approved', 'sent'])->whereBetween('issued_at', [$m, $m->copy()->endOfMonth()])->sum('total')];
        }

        return view('ops.contacts.show', [
            'customer' => $customer,
            'ytd' => $customer->paidYtd(),
            'openAr' => (float) Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['approved', 'sent'])->sum('total'),
            'threads' => $threads,
            'filters' => ['location' => $locationId ?: null, 'job' => $jobId ?: null],
            'series' => $series,
            'maxSeries' => max(1.0, max(array_merge(array_column($series, 'paid'), array_column($series, 'open')))),
            'segments' => config('batsons.customer_segments', []),
            'owners' => User::query()->where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(),
            'reminders' => $customer->reminders()->whereIn('status', Reminder::OPEN)->orderBy('due_at')->take(5)->get(),
            'canEdit' => ! $this->actor($request)->isField(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'segment' => ['nullable', Rule::in(config('batsons.customer_segments', []))],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', app('currentTenantId'))],
            'corp_poc_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('customer_id', $customer->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'payment_terms' => ['nullable', Rule::in(array_keys(config('batsons.payment_terms', [])))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $customer->fill(array_map(fn ($v) => $v === '' ? null : $v, $data))->save();

        return back()->with('status', 'Business record updated.');
    }

    public function storeContact(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'role' => ['nullable', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:255']]);
        Contact::create($data + ['customer_id' => $customer->id]);

        return back()->with('status', "{$data['name']} added.");
    }

    public function storeLocation(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'], 'address' => ['required', 'string', 'max:200'], 'type' => ['nullable', Rule::in(['job', 'billing'])],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('customer_id', $customer->id)],
            'access_notes' => ['nullable', 'string', 'max:500'],
        ]);
        ServiceLocation::create(['customer_id' => $customer->id, 'label' => $data['label'] ?? null, 'address' => $data['address'], 'type' => $data['type'] ?? 'job', 'contact_id' => $data['contact_id'] ?? null, 'access_notes' => $data['access_notes'] ?? null]);

        return back()->with('status', 'Location added.');
    }

    public function setLocationContact(Request $request, ServiceLocation $location): RedirectResponse
    {
        $data = $request->validate(['contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('customer_id', $location->customer_id)]]);
        $location->forceFill(['contact_id' => $data['contact_id'] ?: null])->save();

        return back()->with('status', 'Local contact set.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
