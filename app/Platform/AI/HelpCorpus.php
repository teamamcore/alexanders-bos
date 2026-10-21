<?php

declare(strict_types=1);

namespace App\Platform\AI;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * SOW §3.16 — in-app help is grounded in the repo docs (overview, spec,
 * security overview, parameters, design notes), split into headed sections.
 */
final class HelpCorpus
{
    /** @return list<array{title: string, body: string, source: string}> */
    public static function sections(): array
    {
        $out = [];
        foreach (config('ai.help_sources', []) as $rel) {
            $path = base_path($rel);
            if (! File::exists($path)) {
                continue;
            }
            $current = ['title' => Str::afterLast($rel, '/'), 'body' => '', 'source' => $rel];
            foreach (preg_split('/\r\n|\r|\n/', File::get($path)) ?: [] as $line) {
                if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
                    if (trim($current['body']) !== '') {
                        $out[] = $current;
                    }
                    $current = ['title' => trim($m[1]), 'body' => '', 'source' => $rel];
                } else {
                    $current['body'] .= $line."\n";
                }
            }
            if (trim($current['body']) !== '') {
                $out[] = $current;
            }
        }

        return $out;
    }

    /** Page-aware hint for the assistant (route prefix → what the page is for). */
    public static function pageContext(?string $routeName): string
    {
        $prefix = explode('.', (string) $routeName)[0];
        $map = [
            'work-orders' => 'the Work Order parent page: status pill, scope of work, billables, notes, scope-update pings',
            'schedule' => 'the dispatch board: roster, All Day/AM/PM lanes, W.O.S panel, drag-and-drop, emergencies',
            'contacts' => 'the Contacts module: business, locations with local contacts, comms, A/R',
            'parts' => 'Parts & inventory: catalog, nicknames, deliveries, stock adjustments',
            'equipment' => 'Billable equipment: usage, maintenance, check-out, depreciation',
            'fleet' => 'Fleet: mileage, service intervals, renewals, costs',
            'reports' => 'Reports: A/R aging, job costing, profitability, trends',
            'reminders' => 'Recurring service reminders',
            'messages' => 'Customer communication: texts, templates, calls',
            'admin' => 'Administration: users, qualifications, reminder types, terminology, security',
        ];

        return $map[$prefix] ?? 'the Batsons BOS dashboard';
    }
}
