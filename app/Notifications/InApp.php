<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * SOW §3.12 — the one in-app notification shape: a kind (assignment,
 * schedule_change, customer_reply, issue, delivery, message, digest, …),
 * a title, an optional body, and where to go when tapped.
 */
final class InApp extends Notification
{
    /** @param  array<string, mixed>  $meta */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly array $meta = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }
}
