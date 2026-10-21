<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AI\AskHelp;
use App\Actions\AI\SearchEverything;
use App\Actions\AI\SuggestTagsFor;
use App\Models\AiUsage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Platform\AI\AI;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Module 3.16 — search, help, tags (AI-assisted, with an offline fallback). */
final class AssistController extends Controller
{
    public function search(Request $request): View
    {
        $q = (string) $request->query('q', '');
        $result = SearchEverything::run($q, $this->actor($request)->isField());

        return view('ops.search', ['q' => $q] + $result + ['types' => SearchEverything::TYPES]);
    }

    public function help(Request $request): View
    {
        $q = (string) $request->query('q', '');
        $from = (string) $request->query('from', '');
        $result = $q !== '' ? AskHelp::run($q, $from ?: null) : null;

        return view('ops.help', ['q' => $q, 'from' => $from, 'result' => $result, 'provider' => AI::assistant()->name(),
            'starters' => ['How do I put a work order on hold?', 'What does the tech see on a work order?', 'How are customers notified when a job is scheduled?', 'Where do I change the reminder window for backflow tests?', 'What data must never be stored in the system?', 'How does the invoice tally get calculated?']]);
    }

    public function suggestTags(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $tags = SuggestTagsFor::run($workOrder, true);

        return back()->with('status', $tags ? 'Tags: '.implode(', ', $tags).'.' : 'No tags suggested yet — add a note or scope first.');
    }

    public function addTag(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:30']]);
        $workOrder->applyTags([$data['tag']], 'user');

        return back()->with('status', 'Tag added.');
    }

    public function removeTag(Request $request, WorkOrder $workOrder, string $slug): RedirectResponse
    {
        $workOrder->removeTag($slug);

        return back()->with('status', 'Tag removed.');
    }

    public function usage(Request $request): View
    {
        $since = today()->startOfMonth();
        $rows = AiUsage::query()->where('created_at', '>=', $since)->get();

        return view('ops.admin.ai-usage', [
            'enabled' => AI::enabled(),
            'provider' => AI::assistant()->name(),
            'model' => config('ai.model'),
            'month' => $since,
            'byFeature' => $rows->groupBy('feature')->map(fn ($g) => ['calls' => $g->count(), 'input' => $g->sum('input_tokens'), 'output' => $g->sum('output_tokens'), 'cost' => (float) $g->sum('cost_usd'), 'failed' => $g->where('ok', false)->count()]),
            'total' => ['calls' => $rows->count(), 'cost' => (float) $rows->sum('cost_usd'), 'tokens' => $rows->sum('input_tokens') + $rows->sum('output_tokens')],
            'recent' => AiUsage::query()->with('user')->latest()->take(30)->get(),
            'transcription' => config('ai.transcription_driver'),
        ]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
