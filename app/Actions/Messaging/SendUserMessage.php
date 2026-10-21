<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Job;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\WorkOrder;
use App\Platform\Notifications\Notifier;
use DomainException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.12 — in-app message from one User to another; the recipient is notified. */
final class SendUserMessage
{
    use AsAction;

    public function handle(User $sender, User $recipient, string $body, ?Job $job = null, ?WorkOrder $workOrder = null): UserMessage
    {
        if ($recipient->tenant_id !== $sender->tenant_id) {
            throw new DomainException('You can only message people in your own company.');
        }
        if ($recipient->is($sender)) {
            throw new DomainException('You cannot message yourself.');
        }
        if (! $recipient->is_active) {
            throw new DomainException("{$recipient->name} is deactivated.");
        }
        $body = trim($body);
        if ($body === '') {
            throw new DomainException('Message is empty.');
        }

        $message = UserMessage::create([
            'tenant_id' => $sender->tenant_id,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => $body,
            'job_id' => $job->id ?? $workOrder?->job_id,
            'work_order_id' => $workOrder?->id,
        ]);

        Notifier::send($recipient, 'message', "Message from {$sender->name}", Str::limit($body, 120), route('inbox.thread', $sender), ['user_message_id' => $message->id]);

        return $message;
    }
}
