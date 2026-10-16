<?php

declare(strict_types=1);

namespace App\Actions\Proposals;

use App\Actions\WorkOrders\AddWorkOrder;
use App\Models\Job;
use App\Models\Proposal;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.5: a won proposal converts to a job and work order, carrying its line
 * items over. Links back via proposal.converted_job_id and marks it 'won'.
 * Connects the Proposals module into Jobs (§3.2) → Scheduling/Invoicing.
 */
final class ConvertProposalToJob
{
    use AsAction;

    public function handle(Proposal $proposal): Job
    {
        if ($proposal->converted_job_id) {
            throw new DomainException("Proposal #{$proposal->id} is already converted (job #{$proposal->converted_job_id}).");
        }

        $job = Job::create([
            'tenant_id' => $proposal->tenant_id,
            'customer_id' => $proposal->customer_id,
            'title' => $proposal->title,
            'status' => 'open',
        ]);

        // Carry the proposal's work into a first work order.
        AddWorkOrder::run($job, null, $this->lineItemSummary($proposal), 'morning');

        $proposal->update(['stage' => 'won', 'converted_job_id' => $job->id]);

        return $job;
    }

    private function lineItemSummary(Proposal $proposal): ?string
    {
        $items = collect($proposal->line_items ?? [])
            ->map(fn ($li) => trim((string) ($li['description'] ?? '')))
            ->filter()
            ->implode('; ');

        return $items !== '' ? "From proposal: {$items}" : null;
    }
}
