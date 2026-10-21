<?php

declare(strict_types=1);

namespace App\Platform\Notifications;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\InApp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * SOW §3.12 — event-based in-app notifications replacing internal email.
 * Everything funnels through here so a push / email channel can be added
 * later without touching the call sites.
 */
final class Notifier
{
    /**
     * @param  iterable<User>|User  $users
     * @param  array<string, mixed>  $meta
     */
    public static function send(iterable|User $users, string $kind, string $title, ?string $body = null, ?string $url = null, array $meta = []): int
    {
        $list = $users instanceof User ? [$users] : collect($users)->filter()->unique('id')->values()->all();
        if ($list === []) {
            return 0;
        }

        Notification::send($list, new InApp($kind, $title, $body, $url, $meta));

        return count($list);
    }

    /**
     * Office + administrator users of a tenant (the people who used to get the internal email).
     *
     * @return Collection<int, User>
     */
    public static function office(int $tenantId, ?int $except = null): Collection
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', [Role::Office, Role::Administrator])
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->get();
    }

    /** @return Collection<int, User> */
    public static function administrators(int $tenantId): Collection
    {
        return User::query()->where('tenant_id', $tenantId)->where('is_active', true)->where('role', Role::Administrator)->get();
    }

    /**
     * Send once per day per kind to a user (used by the digest so a reminder
     * doesn't pile up every time the command runs).
     *
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $meta
     */
    public static function daily(iterable $users, string $kind, string $title, ?string $body = null, ?string $url = null, array $meta = []): int
    {
        $sent = 0;
        foreach ($users as $user) {
            $already = $user->notifications()
                ->where('type', InApp::class)
                ->where('data->kind', $kind)
                ->whereDate('created_at', today())
                ->exists();
            if (! $already) {
                $sent += self::send($user, $kind, $title, $body, $url, $meta);
            }
        }

        return $sent;
    }
}
