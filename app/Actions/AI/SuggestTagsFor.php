<?php

declare(strict_types=1);

namespace App\Actions\AI;

use App\Models\CallTranscript;
use App\Models\Job;
use App\Models\Tag;
use App\Models\WorkOrder;
use App\Platform\AI\AI;
use App\Platform\AI\Assistant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.16 — AI-assisted tagging. Builds the text a record "is about",
 * asks the assistant for tags from the controlled vocabulary (plus the tags
 * already in use), and optionally applies them.
 *
 * @return list<string>
 */
final class SuggestTagsFor
{
    use AsAction;

    /** @return list<string> */
    public function handle(WorkOrder|Job|CallTranscript $record, bool $apply = true): array
    {
        [$text, $kind] = match (true) {
            $record instanceof WorkOrder => [implode("\n", array_filter([
                $record->job?->title, $record->customer_notes, $record->scope_office, $record->work_performed, $record->work_type,
                $record->isEmergency() ? 'emergency' : null, $record->on_hold ? 'on hold '.$record->holdReasonLabel() : null,
                $record->notes()->whereIn('kind', ['tech_note', 'field_finding', 'scope_update'])->latest()->take(10)->pluck('body')->implode("\n"),
            ])), 'work order'],
            $record instanceof Job => [implode("\n", array_filter([$record->title, $record->customer?->name])), 'job'],
            default => [(string) ($record->transcript ?? $record->summary ?? ''), 'phone call'],
        };

        if (trim($text) === '') {
            return [];
        }

        $vocab = array_values(array_unique(array_merge(config('ai.tag_vocabulary', []), Tag::query()->pluck('slug')->all())));
        $tags = AI::run('tagging', fn (Assistant $a) => $a->suggestTags($text, $vocab, $kind));
        $tags = array_values(array_unique(array_filter(array_map(fn ($t) => Tag::normalize((string) $t), $tags), fn ($t) => $t !== '' && strlen($t) <= 30)));

        if ($apply && $tags !== []) {
            $record->applyTags($tags, 'ai');
        }

        return $tags;
    }
}
