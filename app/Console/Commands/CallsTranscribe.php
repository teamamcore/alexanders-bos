<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\AI\SummarizeCall;
use App\Models\CallTranscript;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * SOW §3.16 — turn uploaded call audio into transcripts, then summarize.
 * Claude does not accept audio, so transcription goes through a pluggable
 * speech-to-text driver (config ai.transcription_driver). With 'none' the
 * command only reports what is waiting; summaries still run for any call
 * whose transcript text arrived another way.
 */
final class CallsTranscribe extends Command
{
    protected $signature = 'calls:transcribe {--limit=25 : Max calls per run}';

    protected $description = 'Transcribe pending call recordings (STT driver) and summarize transcripts that have no summary yet';

    public function handle(): int
    {
        $driver = (string) config('ai.transcription_driver', 'none');
        $limit = max(1, (int) $this->option('limit'));
        $summarized = 0;
        $pending = 0;

        foreach (Tenant::query()->get() as $tenant) {
            app()->instance('currentTenantId', $tenant->id);

            $pending += CallTranscript::query()->where('status', 'pending')->whereNotNull('audio_path')->count();

            $calls = CallTranscript::query()->whereNotNull('transcript')->whereNull('summarized_at')->latest('called_at')->take($limit)->get();
            foreach ($calls as $call) {
                SummarizeCall::run($call);
                $summarized++;
            }
        }

        $this->info("Summarized {$summarized} call(s).");
        if ($pending > 0) {
            $driver === 'none'
                ? $this->warn("{$pending} recording(s) waiting — no speech-to-text driver configured (AI_TRANSCRIPTION_DRIVER=none).")
                : $this->warn("{$pending} recording(s) waiting — driver '{$driver}' is not wired yet.");
        }

        return self::SUCCESS;
    }
}
