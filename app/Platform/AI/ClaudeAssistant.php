<?php

declare(strict_types=1);

namespace App\Platform\AI;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\Message;
use RuntimeException;

/**
 * SOW §3.16 — the Claude-backed assistant (official PHP SDK). Every method
 * is a single structured-output call: a system prompt that pins the task, a
 * JSON schema for the answer, and low effort because these are routine jobs.
 * Token usage accumulates until AI::run() takes it for the usage meter.
 */
final class ClaudeAssistant implements Assistant
{
    private ?Client $client = null;

    /** @var array{model: ?string, input: int, output: int, cache_read: int} */
    private array $usage = ['model' => null, 'input' => 0, 'output' => 0, 'cache_read' => 0];

    public function name(): string
    {
        return 'claude';
    }

    /** @return array{model: ?string, input: int, output: int, cache_read: int} */
    public function takeUsage(): array
    {
        $u = $this->usage;
        $this->usage = ['model' => null, 'input' => 0, 'output' => 0, 'cache_read' => 0];

        return $u;
    }

    public function planSearch(string $question, array $entityTypes): array
    {
        $r = $this->json(
            'You turn a question typed into a plumbing company\'s office system into a search plan. '
            .'Entity types available: '.implode(', ', $entityTypes).'. Extract: keywords (distinct search terms, singular nouns, no stop words, max 6), '
            .'entities (only the types worth searching; all types if unclear), numbers (record numbers like WO-1001, J-12, INV-7, kept uppercase), '
            .'tags (hashtags or obvious topic tags, lowercase, hyphenated), intent (lookup | search | question).',
            $question,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['keywords', 'entities', 'numbers', 'tags', 'intent'], 'properties' => [
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'entities' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $entityTypes]],
                'numbers' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'intent' => ['type' => 'string', 'enum' => ['lookup', 'search', 'question']],
            ]],
        );

        return [
            'keywords' => $this->strings($r['keywords'] ?? []),
            'entities' => array_values(array_intersect($this->strings($r['entities'] ?? []), $entityTypes)) ?: $entityTypes,
            'numbers' => array_map(strtoupper(...), $this->strings($r['numbers'] ?? [])),
            'tags' => $this->strings($r['tags'] ?? []),
            'intent' => in_array($r['intent'] ?? null, ['lookup', 'search', 'question'], true) ? $r['intent'] : 'search',
        ];
    }

    public function answerSearch(string $question, array $hits): string
    {
        if ($hits === []) {
            return 'Nothing matched. Try a work order number, a customer name, or a word from the notes.';
        }
        $r = $this->json(
            'You answer an office user\'s question using ONLY the search hits provided (JSON). One or two plain sentences, no markdown, no invented facts. '
            .'Name the most relevant records by title. If the hits do not answer the question, say what was found instead.',
            "Question: {$question}\n\nHits:\n".json_encode($hits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['answer'], 'properties' => ['answer' => ['type' => 'string']]],
        );

        return trim((string) ($r['answer'] ?? ''));
    }

    public function suggestTags(string $text, array $vocabulary, string $kind = 'work order'): array
    {
        $r = $this->json(
            "You tag a plumbing {$kind} for later search. Prefer tags from this vocabulary: ".implode(', ', $vocabulary).'. '
            .'You may add at most two new tags when clearly warranted. Return 2–6 lowercase, hyphenated tags, most specific first. Never tag pricing or people\'s names.',
            $text,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['tags'], 'properties' => ['tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6]]],
        );

        return array_slice($this->strings($r['tags'] ?? []), 0, 6);
    }

    public function help(string $question, array $sections, string $pageContext): array
    {
        // Keep the prompt bounded: ship the 12 most lexically relevant sections.
        $ranked = (new OfflineAssistant)->rankSections($question, $sections, 12);
        $docs = implode("\n\n", array_map(fn ($s) => "### {$s['title']}\n".trim($s['body']), $ranked));

        $r = $this->json(
            'You are the in-app help for Batsons BOS, a plumbing business operating system. Answer the user\'s question using ONLY the guide sections provided. '
            ."The user is on {$pageContext}. Be concrete and brief (under 150 words), plain text with short paragraphs; name the screen or button to use. "
            .'If the guide does not cover it, say so and suggest asking the office. List the section titles you relied on.',
            "Question: {$question}\n\nGuide sections:\n\n{$docs}",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['answer', 'sources'], 'properties' => [
                'answer' => ['type' => 'string'], 'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]],
        );

        return ['answer' => trim((string) ($r['answer'] ?? '')), 'sources' => $this->strings($r['sources'] ?? [])];
    }

    public function summarizeCall(string $transcript, string $context = ''): array
    {
        $r = $this->json(
            'You summarize phone calls for a plumbing company\'s office. Produce: summary (2–3 plain sentences: who called, about what, what was agreed), '
            .'action_items (imperative, one per line item, max 6, only things someone must actually do), sentiment (positive | neutral | negative, from the caller\'s tone), '
            .'tags (2–5 lowercase hyphenated topic tags such as water-heater, leak, quote-requested). Never include card numbers or other payment details even if spoken.',
            ($context !== '' ? $context."\n\n" : '').'Transcript:\n'.$transcript,
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['summary', 'action_items', 'sentiment', 'tags'], 'properties' => [
                'summary' => ['type' => 'string'],
                'action_items' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 6],
                'sentiment' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
            ]],
        );

        return [
            'summary' => trim((string) ($r['summary'] ?? '')),
            'action_items' => $this->strings($r['action_items'] ?? []),
            'sentiment' => in_array($r['sentiment'] ?? null, ['positive', 'neutral', 'negative'], true) ? $r['sentiment'] : 'neutral',
            'tags' => $this->strings($r['tags'] ?? []),
        ];
    }

    // ---- plumbing ----------------------------------------------------------

    /**
     * One structured-output request. Throws on API failure so AI::run() can
     * meter the failure and fall back to the offline assistant.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function json(string $system, string $user, array $schema): array
    {
        try {
            $message = $this->client()->messages->create(
                maxTokens: (int) config('ai.max_tokens', 4000),
                messages: [['role' => 'user', 'content' => $user]],
                model: (string) config('ai.model'),
                outputConfig: [
                    'effort' => (string) config('ai.effort', 'low'),
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                system: $system,
                requestOptions: ['timeout' => (float) config('ai.timeout', 45)],
            );
        } catch (RateLimitException $e) {
            throw new RuntimeException('Claude rate limit: '.$e->getMessage(), 0, $e);
        } catch (APIStatusException $e) {
            throw new RuntimeException('Claude API error: '.$e->getMessage(), 0, $e);
        } catch (APIConnectionException $e) {
            throw new RuntimeException('Claude unreachable: '.$e->getMessage(), 0, $e);
        }

        $this->meter($message);

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        if ($message->stopReason === 'refusal' || trim($text) === '') {
            throw new RuntimeException('Claude returned no answer'.($message->stopReason ? " ({$message->stopReason})" : '').'.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Claude returned non-JSON output.');
        }

        return $decoded;
    }

    private function meter(Message $message): void
    {
        $this->usage['model'] = $message->model;
        $this->usage['input'] += $message->usage->inputTokens;
        $this->usage['output'] += $message->usage->outputTokens;
        $this->usage['cache_read'] += $message->usage->cacheReadInputTokens ?? 0;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: (string) config('ai.api_key'));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $values), fn ($v) => $v !== ''));
    }
}
