<?php

declare(strict_types=1);

namespace App\Platform\AI;

use App\Models\AiUsage;
use Throwable;

/**
 * Resolver + meter. Picks the configured assistant (Claude when a key is
 * present, offline otherwise), times every call, and records usage for the
 * Exhibit A monthly charge. If Claude fails, the offline assistant answers so
 * the feature never breaks in front of a user.
 */
final class AI
{
    public static function enabled(): bool
    {
        return config('ai.driver') === 'claude' && (string) config('ai.api_key') !== '';
    }

    public static function assistant(): Assistant
    {
        if (self::enabled()) {
            return app(ClaudeAssistant::class);
        }

        return app(OfflineAssistant::class);
    }

    /**
     * Run one assistant method with metering + offline fallback.
     *
     * @template T
     *
     * @param  callable(Assistant): T  $call
     * @return T
     */
    public static function run(string $feature, callable $call, ?int $userId = null): mixed
    {
        $assistant = self::assistant();
        $started = hrtime(true);

        try {
            $result = $call($assistant);
            self::record($feature, $assistant, $started, true, null, $userId);

            return $result;
        } catch (Throwable $e) {
            self::record($feature, $assistant, $started, false, $e->getMessage(), $userId);
            if ($assistant instanceof OfflineAssistant) {
                throw $e;
            }
            report($e);

            return $call(app(OfflineAssistant::class));
        }
    }

    private static function record(string $feature, Assistant $assistant, int $started, bool $ok, ?string $error, ?int $userId): void
    {
        $usage = $assistant instanceof ClaudeAssistant ? $assistant->takeUsage() : ['model' => null, 'input' => 0, 'output' => 0, 'cache_read' => 0];
        $model = $usage['model'] ?? ($assistant instanceof ClaudeAssistant ? (string) config('ai.model') : null);

        AiUsage::create([
            'tenant_id' => app()->bound('currentTenantId') ? (int) app('currentTenantId') : null,
            'user_id' => $userId ?? auth()->id(),
            'feature' => $feature,
            'provider' => $assistant->name(),
            'model' => $model,
            'input_tokens' => $usage['input'],
            'output_tokens' => $usage['output'],
            'cache_read_tokens' => $usage['cache_read'],
            'cost_usd' => $model ? AiUsage::cost($model, $usage['input'], $usage['output'], $usage['cache_read']) : 0,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'ok' => $ok,
            'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);
    }
}
