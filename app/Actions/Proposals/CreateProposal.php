<?php

declare(strict_types=1);

namespace App\Actions\Proposals;

use App\Models\Customer;
use App\Models\Proposal;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.5: build a time-and-materials proposal for a customer. Total is the
 * sum of its line items (labor + materials + equipment). Enters the pipeline
 * at the 'draft' stage.
 *
 * @see MoveProposalStage, ConvertProposalToJob
 */
final class CreateProposal
{
    use AsAction;

    /** @param  array<int, array{description?: string, amount?: float|int|string}>  $lineItems */
    public function handle(Customer $customer, string $title, array $lineItems = []): Proposal
    {
        $total = array_sum(array_map(static fn ($li) => (float) ($li['amount'] ?? 0), $lineItems));

        return Proposal::create([
            'customer_id' => $customer->id,
            'title' => trim($title),
            'stage' => 'draft',
            'line_items' => $lineItems,
            'total' => $total,
        ]);
    }
}
