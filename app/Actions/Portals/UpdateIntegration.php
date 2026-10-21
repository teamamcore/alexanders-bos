<?php

declare(strict_types=1);

namespace App\Actions\Portals;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\VendorPortalIntegration;
use DomainException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.14 — configure a portal: status (planned → building → live, or
 * blocked when the provider refuses access, which stops it counting toward
 * the five), credentials (encrypted), default customer, notes. Audited
 * without the secrets.
 *
 * @param  array<string, mixed>  $data
 */
final class UpdateIntegration
{
    use AsAction;

    /** @param  array<string, mixed>  $data */
    public function handle(VendorPortalIntegration $integration, array $data, User $actor): VendorPortalIntegration
    {
        $status = (string) ($data['status'] ?? $integration->status);
        if (! in_array($status, config('batsons.vendor_portals.statuses', []), true)) {
            throw new DomainException("Unknown status: {$status}");
        }
        if ($status === 'blocked' && trim((string) ($data['blocked_reason'] ?? '')) === '') {
            throw new DomainException('Say why the portal is blocked (the written notice to the Client needs it).');
        }

        $credentials = $integration->credentials ?? [];
        foreach (config("portals.adapters.{$integration->adapter}.connection_fields", []) as $field) {
            if (array_key_exists($field, $data) && (string) $data[$field] !== '') {
                $credentials[$field] = (string) $data[$field]; // blank = keep existing secret
            }
        }

        $integration->fill([
            'name' => trim((string) ($data['name'] ?? $integration->name)) ?: $integration->name,
            'status' => $status,
            'go_live_at' => $data['go_live_at'] ?? $integration->go_live_at,
            'blocked_reason' => $status === 'blocked' ? trim((string) $data['blocked_reason']) : null,
            'counts_toward_included' => $status !== 'blocked',
            'default_customer_id' => array_key_exists('default_customer_id', $data) ? ($data['default_customer_id'] ?: null) : $integration->default_customer_id,
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $integration->notes,
            'credentials' => $credentials,
        ]);
        if (! $integration->webhook_token) {
            $integration->webhook_token = Str::random(40);
        }
        $integration->save();

        AuditLog::record('portal.updated', $integration, ['status' => $status, 'configured' => $integration->isConfigured(), 'by' => $actor->id]);

        return $integration;
    }
}
