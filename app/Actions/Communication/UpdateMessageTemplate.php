<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\AuditLog;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.4 — configurable message templates (tenant override of the platform default). */
final class UpdateMessageTemplate
{
    use AsAction;

    public function handle(Tenant $tenant, string $key, string $body, string $channel = 'sms', ?string $subject = null, bool $isActive = true, ?User $actor = null): MessageTemplate
    {
        if (! array_key_exists($key, config('batsons.message_templates', []))) {
            throw new DomainException("Unknown message template: {$key}");
        }
        if (! in_array($channel, ['sms', 'email', 'both'], true)) {
            throw new DomainException("Unknown channel: {$channel}");
        }
        if (trim($body) === '') {
            throw new DomainException('Template body cannot be empty.');
        }

        $template = MessageTemplate::query()->firstOrNew(['key' => $key]);
        $before = $template->exists ? $template->only(['body', 'channel', 'subject', 'is_active']) : null;

        $template->fill([
            'tenant_id' => $tenant->id,
            'name' => config("batsons.message_templates.{$key}.name", ucwords(str_replace('_', ' ', $key))),
            'channel' => $channel,
            'subject' => $subject ?: null,
            'body' => trim($body),
            'is_active' => $isActive,
        ])->save();

        AuditLog::record('message_template.updated', $template, ['key' => $key, 'before' => $before, 'after' => $template->only(['body', 'channel', 'subject', 'is_active']), 'by' => $actor?->id]);

        return $template;
    }
}
