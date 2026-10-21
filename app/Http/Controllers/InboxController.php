<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\RouteInboundEmail;
use App\Actions\Messaging\SendUserMessage;
use App\Models\Customer;
use App\Models\InboundEmail;
use App\Models\Job;
use App\Models\User;
use App\Models\UserMessage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Module 3.12 — in-app messaging between Users + the inbound email queue. */
final class InboxController extends Controller
{
    public function index(Request $request, ?User $with = null): View
    {
        $me = $this->actor($request);
        $people = User::query()->where('tenant_id', $me->tenant_id)->where('is_active', true)->whereKeyNot($me->id)->orderBy('name')->get();

        // Threads: latest message + unread count per counterpart.
        $all = UserMessage::query()->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))->latest()->get();
        $threads = $all->groupBy(fn (UserMessage $m) => $m->sender_id === $me->id ? $m->recipient_id : $m->sender_id)
            ->map(fn ($msgs, $otherId) => [
                'user' => $people->firstWhere('id', (int) $otherId) ?? User::find($otherId),
                'last' => $msgs->first(),
                'unread' => $msgs->where('recipient_id', $me->id)->whereNull('read_at')->count(),
            ])->filter(fn (array $t): bool => $t['user'] instanceof User)->sortByDesc(fn (array $t) => $t['last']?->created_at)->values();

        $thread = collect();
        if ($with instanceof User) {
            abort_unless($with->tenant_id === $me->tenant_id, 404);
            $thread = UserMessage::query()->between($me->id, $with->id)->with('sender')->oldest()->get();
            UserMessage::query()->where('sender_id', $with->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return view('ops.inbox.index', [
            'me' => $me,
            'people' => $people,
            'threads' => $threads,
            'with' => $with,
            'thread' => $thread,
            'unroutedEmails' => $me->isField() ? 0 : InboundEmail::query()->unrouted()->count(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', app('currentTenantId'))],
            'body' => ['required', 'string', 'max:2000'],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs_records', 'id')->where('tenant_id', app('currentTenantId'))],
        ]);
        $recipient = User::findOrFail((int) $data['recipient_id']);

        try {
            SendUserMessage::run($this->actor($request), $recipient, $data['body'], ! empty($data['job_id']) ? Job::find((int) $data['job_id']) : null);
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->route('inbox.thread', $recipient);
    }

    public function email(Request $request): View
    {
        return view('ops.inbox.email', [
            'emails' => InboundEmail::query()->with(['customer', 'job'])->latest('received_at')->take(100)->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'jobs' => Job::query()->with('customer')->latest()->take(50)->get(),
            'inboundAddress' => config('messaging.inbound_email_address'),
        ]);
    }

    public function routeEmail(Request $request, InboundEmail $email): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app('currentTenantId'))],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs_records', 'id')->where('tenant_id', app('currentTenantId'))],
            'archive' => ['nullable', 'boolean'],
        ]);

        try {
            RouteInboundEmail::run($email, $this->actor($request),
                ! empty($data['customer_id']) ? Customer::find((int) $data['customer_id']) : null,
                ! empty($data['job_id']) ? Job::find((int) $data['job_id']) : null,
                (bool) ($data['archive'] ?? false));
        } catch (DomainException $e) {
            return back()->withErrors(['customer_id' => $e->getMessage()]);
        }

        return back()->with('status', ! empty($data['archive']) ? 'Email archived.' : 'Email routed to the customer record.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
