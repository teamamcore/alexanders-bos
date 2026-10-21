<?php

declare(strict_types=1);

namespace App\Platform\AI;

/**
 * SOW §3.16 — the one contract behind search, tagging, help and call
 * summaries. ClaudeAssistant talks to the Anthropic API; OfflineAssistant is
 * the deterministic fallback used when no key is configured (and in tests).
 *
 * @phpstan-type SearchPlan array{keywords: list<string>, entities: list<string>, numbers: list<string>, tags: list<string>, intent: string}
 * @phpstan-type HelpAnswer array{answer: string, sources: list<string>}
 * @phpstan-type CallSummary array{summary: string, action_items: list<string>, sentiment: string, tags: list<string>}
 */
interface Assistant
{
    public function name(): string;

    /**
     * Turn a natural-language question into a search plan.
     *
     * @param  list<string>  $entityTypes  the kinds we can search
     * @return SearchPlan
     */
    public function planSearch(string $question, array $entityTypes): array;

    /**
     * One-paragraph answer over the hits we found (grounded; no invention).
     *
     * @param  list<array{type: string, title: string, snippet: string, url: string}>  $hits
     */
    public function answerSearch(string $question, array $hits): string;

    /**
     * @param  list<string>  $vocabulary
     * @return list<string> 2–6 lowercase tags
     */
    public function suggestTags(string $text, array $vocabulary, string $kind = 'work order'): array;

    /**
     * @param  list<array{title: string, body: string}>  $sections  candidate doc sections
     * @return HelpAnswer
     */
    public function help(string $question, array $sections, string $pageContext): array;

    /** @return CallSummary */
    public function summarizeCall(string $transcript, string $context = ''): array;
}
