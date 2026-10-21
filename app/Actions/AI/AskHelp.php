<?php

declare(strict_types=1);

namespace App\Actions\AI;

use App\Platform\AI\AI;
use App\Platform\AI\Assistant;
use App\Platform\AI\HelpCorpus;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.16 — in-app help, grounded in the repo docs and aware of the page
 * the user is on. Returns the answer plus the doc sections it drew from.
 *
 * @return array{answer: string, sources: list<string>, provider: string}
 */
final class AskHelp
{
    use AsAction;

    /** @return array{answer: string, sources: list<string>, provider: string} */
    public function handle(string $question, ?string $routeName = null): array
    {
        $sections = array_map(fn ($s) => ['title' => $s['title'], 'body' => $s['body']], HelpCorpus::sections());
        $r = AI::run('help', fn (Assistant $a) => $a->help(trim($question), $sections, HelpCorpus::pageContext($routeName)));

        return ['answer' => $r['answer'], 'sources' => $r['sources'], 'provider' => AI::assistant()->name()];
    }
}
