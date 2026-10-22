<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\WorkOrderNote;
use Illuminate\Support\Str;

if (! function_exists('term')) {
    /**
     * SOW §3.13 — tenant-configurable terminology.
     * term('work_order') → "Work Order" by default, or whatever the client calls it.
     */
    function term(string $key, bool $plural = false): string
    {
        $label = Tenant::current()?->term($key)
            ?? config("batsons.terminology.{$key}", Str::headline($key));

        return $plural ? Str::plural($label) : $label;
    }
}

if (! function_exists('abbr')) {
    /** SOW §3.13 — tenant-configurable abbreviation. abbr('work_order') → "WO". */
    function abbr(string $key): string
    {
        return Tenant::current()?->abbr($key)
            ?? config("batsons.abbreviations.{$key}", Str::upper(Str::substr($key, 0, 2)));
    }
}

if (! function_exists('bos_page_title')) {
    /** Current page name for the top bar (design notes §1). */
    function bos_page_title(): string
    {
        $name = (string) (request()->route()?->getName() ?? '');
        $map = [
            'dashboard' => 'Dashboard', 'work-orders' => term('work_order', true), 'jobs' => term('job', true), 'schedule' => term('schedule'),
            'contacts' => 'Contacts', 'proposals' => term('proposal', true), 'invoices' => term('invoice', true), 'time' => 'Time & Pay',
            'team' => 'Team', 'parts' => 'Inventory · Parts', 'equipment' => 'Inventory · Equipment', 'fleet' => 'Inventory · Fleet',
            'messages' => 'Customer Communication', 'inbox' => 'Inbox', 'notifications' => 'Notifications', 'reminders' => 'Reminders',
            'reports' => 'Reports', 'portals' => 'Vendor Portals', 'build' => 'Build checklist', 'admin' => 'Administration', 'profile' => 'Profile',
        ];
        $prefix = explode('.', $name)[0];

        return $map[$prefix] ?? ucwords(str_replace(['-', '_', '.'], ' ', $prefix ?: 'Batsons BOS'));
    }
}

if (! function_exists('bos_nav')) {
    /**
     * Sidebar groups for the current user (design notes §1/§4). `is` are route
     * patterns for the active state; `icon` names resolve through <x-icon>.
     *
     * @return list<array{label: string, items: list<array{route: string, is: array<int, string>, label: string, icon: string, show: bool, badge?: int|string|null}>}>
     */
    function bos_nav(): array
    {
        $u = auth()->user();
        if (! $u) {
            return [];
        }
        $field = $u->isField();
        $admin = $u->isAdministrator();
        $pendingScope = $field ? 0 : WorkOrderNote::query()->pendingScopeUpdates()->count();

        return [
            ['label' => 'Operate', 'items' => [
                ['route' => 'dashboard', 'is' => ['dashboard'], 'label' => 'Dashboard', 'icon' => 'dashboard', 'show' => true, 'badge' => $pendingScope ?: null],
                ['route' => 'jobs.index', 'is' => ['jobs.*'], 'label' => term('job', true), 'icon' => 'jobs', 'show' => ! $field],
                ['route' => 'work-orders.index', 'is' => ['work-orders.*'], 'label' => term('work_order', true), 'icon' => 'work_orders', 'show' => true],
                ['route' => 'schedule.index', 'is' => ['schedule.*'], 'label' => term('schedule'), 'icon' => 'schedule', 'show' => true],
                ['route' => 'contacts.index', 'is' => ['contacts.*'], 'label' => 'Contacts', 'icon' => 'contacts', 'show' => ! $field],
                ['route' => 'proposals.index', 'is' => ['proposals.*'], 'label' => term('proposal', true), 'icon' => 'proposals', 'show' => ! $field],
                ['route' => 'invoices.index', 'is' => ['invoices.*'], 'label' => term('invoice', true), 'icon' => 'invoices', 'show' => ! $field],
            ]],
            ['label' => 'People & things', 'items' => [
                ['route' => 'time.index', 'is' => ['time.*'], 'label' => 'Time & Pay', 'icon' => 'time', 'show' => true],
                ['route' => 'team.index', 'is' => ['team.*'], 'label' => 'Team', 'icon' => 'team', 'show' => true],
                ['route' => 'parts.index', 'is' => ['parts.*', 'equipment.*', 'fleet.*'], 'label' => 'Inventory', 'icon' => 'inventory', 'show' => true],
            ]],
            ['label' => 'Talk', 'items' => [
                ['route' => $field ? 'messages.calls' : 'messages.index', 'is' => ['messages.*'], 'label' => 'Messages', 'icon' => 'messages', 'show' => true],
                ['route' => 'inbox.index', 'is' => ['inbox.*'], 'label' => 'Inbox', 'icon' => 'inbox', 'show' => true],
                ['route' => 'reminders.index', 'is' => ['reminders.*'], 'label' => 'Reminders', 'icon' => 'reminders', 'show' => ! $field],
            ]],
            ['label' => 'Insight', 'items' => [
                ['route' => 'reports.index', 'is' => ['reports.*'], 'label' => 'Reports', 'icon' => 'reports', 'show' => ! $field],
                ['route' => 'portals.index', 'is' => ['portals.*'], 'label' => 'Portals', 'icon' => 'portals', 'show' => ! $field],
                ['route' => 'build.index', 'is' => ['build.*'], 'label' => 'Build', 'icon' => 'build', 'show' => true],
                ['route' => 'admin.users', 'is' => ['admin.*'], 'label' => 'Administration', 'icon' => 'admin', 'show' => $admin],
            ]],
        ];
    }
}
