<?php

declare(strict_types=1);

namespace App\Platform\Messaging;

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\WorkOrder;

/**
 * SOW §3.4 — configurable message templates. Platform defaults live in
 * config('batsons.message_templates'); a tenant overrides them in
 * message_templates. Placeholders use {curly_names}; unknown ones render blank.
 */
final class TemplateRenderer
{
    /** @return array{key: string, name: string, channel: string, subject: string|null, body: string, is_active: bool, overridden: bool} */
    public static function template(string $key): array
    {
        $default = config("batsons.message_templates.{$key}");
        $override = MessageTemplate::query()->where('key', $key)->first();

        if (! $default && ! $override) {
            throw new \DomainException("Unknown message template: {$key}");
        }

        return [
            'key' => $key,
            'name' => $override->name ?? $default['name'] ?? ucwords(str_replace('_', ' ', $key)),
            'channel' => $override->channel ?? $default['channel'] ?? 'sms',
            'subject' => $override->subject ?? $default['subject'] ?? null,
            'body' => $override->body ?? $default['body'] ?? '',
            'is_active' => $override ? (bool) $override->is_active : true,
            'overridden' => $override !== null,
        ];
    }

    /** @return array<string, array{key: string, name: string, channel: string, subject: string|null, body: string, is_active: bool, overridden: bool}> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(config('batsons.message_templates', [])) as $key) {
            $out[$key] = self::template($key);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $vars */
    public static function render(string $text, array $vars): string
    {
        $rendered = preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text) ?? $text;

        return trim(preg_replace('/[ \t]{2,}/', ' ', $rendered) ?? $rendered);
    }

    /**
     * Standard placeholders for a work order (customer, job, date, tech, address…).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function variables(?WorkOrder $workOrder = null, array $extra = []): array
    {
        $job = $workOrder?->job;
        $customer = $job?->customer;
        $tenant = Tenant::current();

        $vars = [
            'company' => $tenant->name ?? config('app.name'),
            'customer' => $customer->name ?? '',
            'first_name' => $customer ? explode(' ', trim($customer->name))[0] : '',
            'job' => $job->title ?? '',
            'job_number' => $job->number ?? '',
            'work_order' => $workOrder->number ?? '',
            'date' => $workOrder?->scheduled_date?->format('l, M j') ?? '',
            'window' => $workOrder?->schedule_window ? str_replace('_', ' ', $workOrder->schedule_window) : '',
            'tech' => $workOrder->assignee->name ?? '',
            'address' => $job->serviceLocation->address ?? '',
            'eta' => '',
            'note' => '',
        ];

        return array_merge($vars, $extra);
    }
}
