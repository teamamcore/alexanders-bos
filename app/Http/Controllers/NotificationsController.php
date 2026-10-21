<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Module 3.12 — in-app notifications. */
final class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->actor($request);

        return view('ops.notifications', [
            'notifications' => $user->notifications()->latest()->take(100)->get(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $n = $this->actor($request)->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();
        $url = $n->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->actor($request)->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked read.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
