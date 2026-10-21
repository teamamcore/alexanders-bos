<?php

declare(strict_types=1);

namespace App\Actions\Proposals;

use App\Models\Proposal;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.5: proposal pipeline — draft → sent → follow_up → won | lost. */
final class MoveProposalStage
{
    use AsAction;

    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        'draft' => ['sent', 'lost'],
        'sent' => ['follow_up', 'won', 'lost'],
        'follow_up' => ['sent', 'won', 'lost'],
        'won' => [],
        'lost' => [],
    ];

    public function handle(Proposal $proposal, string $stage): Proposal
    {
        $allowed = self::TRANSITIONS[$proposal->stage] ?? [];

        if (! in_array($stage, $allowed, true)) {
            throw new DomainException("Cannot move proposal from '{$proposal->stage}' to '{$stage}'.");
        }

        $proposal->update(['stage' => $stage]);

        return $proposal->refresh();
    }
}
