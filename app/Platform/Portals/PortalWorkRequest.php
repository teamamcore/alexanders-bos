<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use Illuminate\Support\Carbon;

/** A work request as issued by a customer in its portal (adapter-neutral). */
final readonly class PortalWorkRequest
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public string $externalId,
        public ?string $externalNumber,
        public ?string $customerName,
        public ?string $locationName,
        public ?string $address,
        public ?string $trade,
        public ?string $priority,
        public ?float $nte,
        public ?string $description,
        public ?Carbon $scheduledBy,
        public array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $d */
    public static function fromArray(array $d): self
    {
        $by = $d['scheduled_by'] ?? null;
        if (is_string($by) && str_starts_with($by, '+')) {
            $by = today()->addDays((int) trim($by, '+ days'));
        } elseif (is_string($by) && $by !== '') {
            $by = Carbon::parse($by);
        } else {
            $by = null;
        }

        return new self(
            externalId: (string) ($d['external_id'] ?? $d['id'] ?? ''),
            externalNumber: isset($d['external_number']) ? (string) $d['external_number'] : (isset($d['number']) ? (string) $d['number'] : null),
            customerName: isset($d['customer_name']) ? (string) $d['customer_name'] : null,
            locationName: isset($d['location_name']) ? (string) $d['location_name'] : null,
            address: isset($d['address']) ? (string) $d['address'] : null,
            trade: isset($d['trade']) ? (string) $d['trade'] : null,
            priority: isset($d['priority']) ? (string) $d['priority'] : null,
            nte: isset($d['nte']) && $d['nte'] !== '' ? (float) $d['nte'] : null,
            description: isset($d['description']) ? (string) $d['description'] : null,
            scheduledBy: $by instanceof Carbon ? $by : null,
            raw: $d,
        );
    }
}
