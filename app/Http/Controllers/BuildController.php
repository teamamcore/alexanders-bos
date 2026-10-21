<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Build checklist — the Notion Build Timeline, simplified and in-app, with a
 * link to the live screen for every shipped module (config/build.php).
 */
final class BuildController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $cfg = config('build');

        $items = collect($cfg['items'])->map(function (array $item) use ($user) {
            $link = $item['link'] ?? null;
            if ($link && ! empty($link['admin']) && ! $user->isAdministrator()) {
                $link = null; // admin-only screens aren't linkable for office/field
            }
            [$start, $end] = $item['target'] + [null, null];

            return [
                ...$item,
                'link' => $link,
                'start' => $start ? Carbon::parse($start) : null,
                'end' => $end ? Carbon::parse($end) : null,
                'overdue' => $item['status'] !== 'done' && $end && Carbon::parse($end)->isPast(),
            ];
        });

        $modules = $items->where('phase', 'Build');
        $counts = [
            'done' => $items->where('status', 'done')->count(),
            'in_progress' => $items->where('status', 'in_progress')->count(),
            'not_started' => $items->where('status', 'not_started')->count(),
        ];

        return view('ops.build', [
            'phases' => $cfg['phases'],
            'byPhase' => $items->groupBy('phase'),
            'counts' => $counts,
            'moduleDone' => $modules->where('status', 'done')->count(),
            'moduleTotal' => $modules->count(),
            'asOf' => Carbon::parse($cfg['as_of']),
            'notionUrl' => $cfg['notion_url'],
            'next' => $items->first(fn (array $i) => $i['status'] === 'in_progress')
                ?? $items->where('phase', 'Build')->where('status', 'not_started')->sortBy('start')->first(),
        ]);
    }
}
