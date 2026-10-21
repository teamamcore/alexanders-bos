<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Certification;
use App\Models\Employee;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.11 — record a certification or licence; expiry drives alerts + scheduling indicators. */
final class AddCertification
{
    use AsAction;

    public function handle(
        Employee $employee,
        string $name,
        string $type = 'certification',
        ?string $number = null,
        ?string $issuedAt = null,
        ?string $expiresAt = null,
    ): Certification {
        return $employee->certifications()->create([
            'name' => trim($name),
            'type' => in_array($type, ['certification', 'license'], true) ? $type : 'certification',
            'number' => $number ?: null,
            'issued_at' => $issuedAt ?: null,
            'expires_at' => $expiresAt ?: null,
        ]);
    }
}
