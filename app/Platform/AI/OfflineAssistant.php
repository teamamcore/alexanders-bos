<?php

declare(strict_types=1);

namespace App\Platform\AI;

use Illuminate\Support\Str;

/**
 * Deterministic fallback: keyword planning, rule-based tags, doc-section
 * matching, first-sentence summaries. Keeps every §3.16 feature working with
 * no API key, and makes the behavior testable without the network.
 */
final class OfflineAssistant implements Assistant
{
    private const array STOP = ['the', 'a', 'an', 'and', 'or', 'of', 'for', 'to', 'in', 'on', 'at', 'is', 'are', 'was', 'were', 'what', 'which', 'who', 'show', 'me', 'find', 'all', 'any', 'with', 'from', 'that', 'this', 'has', 'have', 'did', 'do', 'does', 'about', 'last', 'week', 'month', 'today', 'please', 'list', 'get'];

    public function name(): string
    {
        return 'offline';
    }

    public function planSearch(string $question, array $entityTypes): array
    {
        $q = Str::lower($question);
        preg_match_all('/\b(?:wo|j|inv|pr)-?\d+(?:-\d+)?\b/i', $question, $nums);
        preg_match_all('/#(\w[\w-]*)/', $question, $tagm);
        $words = array_values(array_filter(preg_split('/[^a-z0-9\'&]+/', $q) ?: [], fn ($w) => strlen($w) > 2 && ! in_array($w, self::STOP, true) && ! preg_match('/^(wo|j|inv|pr)-?\d+/', $w)));

        $entities = [];
        $hints = ['customer' => ['customer', 'business', 'account', 'client'], 'contact' => ['contact', 'phone', 'email', 'manager'], 'job' => ['job', 'project'],
            'work_order' => ['work order', 'wo', 'ticket', 'scope', 'performed'], 'note' => ['note', 'notes', 'finding'], 'part' => ['part', 'valve', 'fitting', 'sku', 'stock', 'inventory'],
            'invoice' => ['invoice', 'paid', 'overdue', 'a/r', 'balance'], 'message' => ['message', 'text', 'texted', 'said', 'reply', 'email'], 'call' => ['call', 'called', 'transcript', 'voicemail']];
        foreach ($hints as $type => $terms) {
            foreach ($terms as $t) {
                if (str_contains($q, $t) && in_array($type, $entityTypes, true)) {
                    $entities[] = $type;
                    break;
                }
            }
        }

        return [
            'keywords' => array_values(array_unique(array_slice($words, 0, 6))),
            'entities' => array_values(array_unique($entities)) ?: $entityTypes,
            'numbers' => array_map(strtoupper(...), $nums[0]),
            'tags' => array_map(fn ($t) => Str::slug($t), $tagm[1]),
            'intent' => $nums[0] !== [] ? 'lookup' : 'search',
        ];
    }

    public function answerSearch(string $question, array $hits): string
    {
        if ($hits === []) {
            return 'Nothing matched. Try a work order number, a customer name, or a word from the notes.';
        }
        $top = array_slice($hits, 0, 3);
        $parts = array_map(fn ($h) => "{$h['title']} ({$h['type']})", $top);

        return count($hits).' result'.(count($hits) === 1 ? '' : 's').' — top: '.implode('; ', $parts).'.';
    }

    public function suggestTags(string $text, array $vocabulary, string $kind = 'work order'): array
    {
        $t = Str::lower($text);
        $rules = [
            'backflow' => ['backflow', 'rpz', 'double check', 'dcva'], 'sewer' => ['sewer', 'main line', 'stoppage', 'jetter', 'jet '], 'drain' => ['drain', 'clog', 'backing up', 'snake'],
            'water-heater' => ['water heater', 'tankless', 'no hot water'], 'gas' => ['gas line', 'gas leak', 'gas '], 'leak' => ['leak', 'leaking', 'drip', 'flood'],
            'camera' => ['camera', 'video inspect', 'scope the'], 'excavation' => ['excavat', 'dig', 'trench'], 'repipe' => ['repipe', 're-pipe'], 'fixture' => ['toilet', 'faucet', 'sink', 'fixture', 'shower'],
            'emergency' => ['emergency', 'urgent', 'asap', 'flooding'], 'warranty' => ['warranty'], 'callback' => ['callback', 'call back', 'came back'], 'permit' => ['permit'], 'inspection' => ['inspection', 'inspector', 'certif'],
            'recurring' => ['annual', 'recurring', 'every year'], 'parts-needed' => ['need part', 'waiting for parts', 'order part', 'parts needed', 'special order'],
            'quote-requested' => ['quote', 'estimate', 'proposal'], 'customer-approval' => ['approval', 'approve', 'authorized'], 'after-hours' => ['after hours', 'after-hours', 'weekend', 'overtime'], 'safety' => ['safety', 'hazard', 'osha'],
        ];
        $out = [];
        foreach ($rules as $tag => $needles) {
            foreach ($needles as $n) {
                if (str_contains($t, $n)) {
                    $out[] = $tag;
                    break;
                }
            }
        }
        foreach ($vocabulary as $v) {
            if (! in_array($v, $out, true) && str_contains($t, str_replace('-', ' ', $v))) {
                $out[] = $v;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 6);
    }

    public function help(string $question, array $sections, string $pageContext): array
    {
        $best = $this->rankSections($question, $sections, 2);
        if ($best === []) {
            return ['answer' => "I couldn't find that in the built-in guide. Try different words, or ask the office.", 'sources' => []];
        }
        $answer = implode("\n\n", array_map(fn ($s) => '**'.$s['title']."**\n".Str::limit(trim($s['body']), 700), $best));

        return ['answer' => $answer, 'sources' => array_map(fn ($s) => $s['title'], $best)];
    }

    /**
     * Lexical ranking of doc sections (also used to bound the Claude prompt).
     *
     * @param  list<array{title: string, body: string}>  $sections
     * @return list<array{title: string, body: string}>
     */
    public function rankSections(string $question, array $sections, int $limit): array
    {
        $words = array_filter(preg_split('/[^a-z0-9]+/', Str::lower($question)) ?: [], fn ($w) => strlen($w) > 2 && ! in_array($w, self::STOP, true));
        $scored = [];
        foreach ($sections as $s) {
            $hay = Str::lower($s['title'].' '.$s['body']);
            $score = 0;
            foreach ($words as $w) {
                $score += substr_count($hay, $w) * (str_contains(Str::lower($s['title']), $w) ? 3 : 1);
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 's' => $s];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($b) => $b['s'], array_slice($scored, 0, $limit));
    }

    public function summarizeCall(string $transcript, string $context = ''): array
    {
        $first = preg_split('/(?<=[.!?])\s+/', trim($transcript), 2)[0] ?? $transcript;
        $items = [];
        foreach (preg_split('/(?<=[.!?])\s+/', $transcript) ?: [] as $sentence) {
            if (preg_match('/\b(will|need to|needs to|should|schedule|send|call back|follow up|quote|order)\b/i', $sentence)) {
                $items[] = trim($sentence);
            }
        }
        $lower = Str::lower($transcript);
        $sentiment = preg_match('/\b(angry|upset|frustrat|unacceptable|complain|terrible)\b/', $lower) ? 'negative' : (preg_match('/\b(thank|great|appreciate|perfect|happy)\b/', $lower) ? 'positive' : 'neutral');

        return [
            'summary' => mb_strlen($first) > 200 ? mb_substr($first, 0, 197).'…' : $first,
            'action_items' => array_slice($items, 0, 5),
            'sentiment' => $sentiment,
            'tags' => $this->suggestTags($transcript, config('ai.tag_vocabulary', []), 'call'),
        ];
    }
}
