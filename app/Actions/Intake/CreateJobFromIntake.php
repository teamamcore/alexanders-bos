<?php

declare(strict_types=1);

namespace App\Actions\Intake;

use App\Models\Customer;
use App\Models\Job;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.1 → §3.2: single-screen intake — find or create the customer, then
 * open a job. Tenant is applied by the BelongsToTenant global scope.
 */
final class CreateJobFromIntake
{
    use AsAction;

    public function handle(string $customerName, string $jobTitle, ?string $phone = null, ?string $email = null): Job
    {
        $customer = Customer::firstOrCreate(['name' => trim($customerName)]);

        // SOW §3.4 — capture how to reach them so scheduled-day / arrival updates can go out.
        $contactInfo = array_filter(['phone' => trim((string) $phone) ?: null, 'email' => trim((string) $email) ?: null]);
        if ($contactInfo !== []) {
            $customer->fill(array_filter($contactInfo, fn ($v, $k) => empty($customer->{$k}), ARRAY_FILTER_USE_BOTH))->save();
        }

        return Job::create([
            'customer_id' => $customer->id,
            'title' => trim($jobTitle),
            'status' => 'open',
        ]);
    }
}
