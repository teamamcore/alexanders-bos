<?php

declare(strict_types=1);

namespace App\Actions\AI;

use App\Models\CallTranscript;
use App\Platform\AI\AI;
use App\Platform\AI\Assistant;
use Lorisleiva\Actions\Concerns\AsAction;

/** SOW §3.4/§3.16 — summary, action items, sentiment and tags for a call transcript. */
final class SummarizeCall
{
    use AsAction;

    public function handle(CallTranscript $call): CallTranscript
    {
        $text = trim((string) $call->transcript);
        if ($text === '') {
            return $call;
        }

        $context = trim(($call->customer?->name ? 'Customer: '.$call->customer->name.'. ' : '').($call->job?->title ? 'Job: '.$call->job->title.'. ' : ''));
        $r = AI::run('call_summary', fn (Assistant $a) => $a->summarizeCall($text, $context));

        $call->forceFill([
            'summary' => $r['summary'] ?: $call->summary,
            'action_items' => array_slice($r['action_items'], 0, 8),
            'sentiment' => in_array($r['sentiment'], ['positive', 'neutral', 'negative'], true) ? $r['sentiment'] : 'neutral',
            'summarized_at' => now(),
        ])->save();

        if (! empty($r['tags'])) {
            $call->applyTags(array_map(strval(...), (array) $r['tags']), 'ai');
        }

        return $call;
    }
}
