<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Communication\RecordCallTranscript;
use App\Actions\Communication\SendCustomerMessage;
use App\Actions\Communication\UpdateMessageTemplate;
use App\Models\CallTranscript;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\Messaging\TemplateRenderer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.4 — Customer Communication: office monitor, templates, calls. */
final class MessagesController extends Controller
{
    public function index(Request $request): View
    {
        $q = CustomerMessage::query()->with(['customer', 'workOrder', 'sender'])->latest();
        if ($request->filled('customer')) {
            $q->where('customer_id', (int) $request->query('customer'));
        }
        if ($request->filled('direction')) {
            $q->where('direction', (string) $request->query('direction'));
        }
        if ($request->filled('channel')) {
            $q->where('channel', (string) $request->query('channel'));
        }

        return view('ops.messages.index', [
            'messages' => $q->take(200)->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'workOrders' => WorkOrder::query()->with('job.customer')->latest()->take(50)->get(),
            'filters' => $request->only(['customer', 'direction', 'channel']),
            'templates' => TemplateRenderer::all(),
            'autoNotify' => (bool) (Tenant::current()?->settings['auto_notify'] ?? true),
            'stats' => [
                'Sent (7 days)' => CustomerMessage::query()->outbound()->where('created_at', '>=', now()->subDays(7))->count(),
                'Replies (7 days)' => CustomerMessage::query()->inbound()->where('created_at', '>=', now()->subDays(7))->count(),
                'Failed' => CustomerMessage::query()->where('status', 'failed')->count(),
                'Calls logged' => CallTranscript::query()->count(),
            ],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app('currentTenantId'))],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', app('currentTenantId'))],
            'template_key' => ['nullable', 'string', Rule::in(array_keys(config('batsons.message_templates', [])))],
            'body' => ['nullable', 'string', 'max:1000', 'required_without:template_key'],
            'channel' => ['nullable', Rule::in(['sms', 'email'])],
        ]);

        $customer = Customer::query()->with('contacts')->findOrFail((int) $data['customer_id']);
        $wo = ! empty($data['work_order_id']) ? WorkOrder::query()->find((int) $data['work_order_id']) : null;

        try {
            $message = SendCustomerMessage::run($customer, $data['template_key'] ?: null, $data['body'] ?? null, $this->actor($request), $wo, ['note' => $data['body'] ?? ''], null, $data['channel'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        if ($message === null) {
            return back()->withErrors(['customer_id' => "{$customer->name} has no phone or email on file (or the template is turned off)."]);
        }

        return back()->with('status', ucfirst($message->channel)." {$message->status} to {$message->to}.");
    }

    // ---- Templates ----------------------------------------------------------

    public function templates(): View
    {
        return view('ops.messages.templates', [
            'templates' => TemplateRenderer::all(),
            'placeholders' => config('batsons.message_placeholders', []),
            'autoNotify' => (bool) (Tenant::current()?->settings['auto_notify'] ?? true),
            'preview' => TemplateRenderer::variables(WorkOrder::query()->with('job.customer', 'assignee')->latest()->first(), ['eta' => '20 min', 'note' => 'Please clear access to the water heater.']),
        ]);
    }

    public function updateTemplate(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'channel' => ['required', Rule::in(['sms', 'email', 'both'])],
            'subject' => ['nullable', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 403);

        try {
            UpdateMessageTemplate::run($tenant, $key, $data['body'], $data['channel'], $data['subject'] ?? null, (bool) ($data['is_active'] ?? false), $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return back()->with('status', 'Template saved.');
    }

    public function toggleAutoNotify(Request $request): RedirectResponse
    {
        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 403);
        $settings = $tenant->settings ?? [];
        $settings['auto_notify'] = ! (bool) ($settings['auto_notify'] ?? true);
        $tenant->settings = $settings;
        $tenant->save();
        app()->instance('currentTenant', $tenant);

        return back()->with('status', 'Automatic customer updates '.($settings['auto_notify'] ? 'on' : 'off').'.');
    }

    // ---- Calls ----------------------------------------------------------------

    public function calls(Request $request): View
    {
        return view('ops.messages.calls', [
            'calls' => CallTranscript::query()->with(['customer', 'job', 'recorder'])->latest('called_at')->take(100)->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'jobs' => Job::query()->with('customer')->latest()->take(50)->get(),
            'canRecord' => true,
        ]);
    }

    public function storeCall(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app('currentTenantId'))],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs_records', 'id')->where('tenant_id', app('currentTenantId'))],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'phone' => ['nullable', 'string', 'max:40'],
            'duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'transcript' => ['nullable', 'string', 'max:20000'],
            'audio' => ['nullable', 'file', 'mimes:mp3,m4a,wav,ogg,webm', 'max:51200'],
            'consent_noted' => ['nullable', 'boolean'],
            'called_at' => ['nullable', 'date'],
        ]);

        try {
            RecordCallTranscript::run(
                $this->actor($request),
                ! empty($data['customer_id']) ? Customer::find((int) $data['customer_id']) : null,
                $data['transcript'] ?? null,
                (bool) ($data['consent_noted'] ?? false),
                $data['phone'] ?? null,
                isset($data['duration_minutes']) ? (int) round((float) $data['duration_minutes'] * 60) : null,
                $data['direction'],
                ! empty($data['job_id']) ? Job::find((int) $data['job_id']) : null,
                null,
                $request->hasFile('audio') ? $request->file('audio')->store('calls', 'public') : null,
                $data['called_at'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['transcript' => $e->getMessage()]);
        }

        return back()->with('status', 'Call saved.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
