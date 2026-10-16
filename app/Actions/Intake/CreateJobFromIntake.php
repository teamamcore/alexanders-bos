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

    public function handle(string $customerName, string $jobTitle): Job
    {
        $customer = Customer::firstOrCreate(['name' => trim($customerName)]);

        return Job::create([
            'customer_id' => $customer->id,
            'title' => trim($jobTitle),
            'status' => 'open',
        ]);
    }
}
