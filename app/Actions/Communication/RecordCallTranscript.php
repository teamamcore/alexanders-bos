<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\CallTranscript;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.4 — store a phone call transcript against a customer / job. The
 * prototype takes the transcript text directly (or an audio file to be
 * transcribed by the AI provider in §3.16); either way the consent notice
 * must have been given (Client responsibility).
 */
final class RecordCallTranscript
{
    use AsAction;

    public function handle(
        User $recorder,
        ?Customer $customer,
        ?string $transcript,
        bool $consentNoted,
        ?string $phone = null,
        ?int $durationSeconds = null,
        string $direction = 'inbound',
        ?Job $job = null,
        ?WorkOrder $workOrder = null,
        ?string $audioPath = null,
        ?string $calledAt = null,
    ): CallTranscript {
        if (! $consentNoted) {
            throw new DomainException('Confirm the recording/transcription notice was given before saving a call.');
        }
        if (trim((string) $transcript) === '' && $audioPath === null) {
            throw new DomainException('Provide a transcript or an audio recording.');
        }

        $text = trim((string) $transcript);

        return CallTranscript::create([
            'customer_id' => $customer->id ?? $job->customer_id ?? $workOrder?->job?->customer_id,
            'job_id' => $job->id ?? $workOrder?->job_id,
            'work_order_id' => $workOrder?->id,
            'recorded_by' => $recorder->id,
            'direction' => in_array($direction, ['inbound', 'outbound'], true) ? $direction : 'inbound',
            'phone' => $phone ?: null,
            'duration_seconds' => $durationSeconds,
            'audio_path' => $audioPath,
            'transcript' => $text !== '' ? $text : null,
            'summary' => $text !== '' ? $this->summarize($text) : null,
            'status' => $text !== '' ? 'transcribed' : 'pending',
            'consent_noted' => true,
            'called_at' => $calledAt ?: now(),
        ]);
    }

    /** First sentence or so — the AI summary arrives with §3.16. */
    private function summarize(string $text): string
    {
        $first = preg_split('/(?<=[.!?])\s+/', $text, 2)[0] ?? $text;

        return mb_strlen($first) > 160 ? mb_substr($first, 0, 157).'…' : $first;
    }
}
