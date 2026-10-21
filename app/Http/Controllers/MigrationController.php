<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Migration\RunImport;
use App\Models\MigrationBatch;
use App\Models\MigrationRecord;
use App\Models\User;
use App\Platform\Migration\Importer;
use App\Platform\Migration\QuickBooksOnline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** SOW §4 — Data migration (administrators). */
final class MigrationController extends Controller
{
    public function index(): View
    {
        $counts = MigrationRecord::query()->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind');

        return view('ops.admin.migration', [
            'kinds' => Importer::KINDS,
            'qbo' => QuickBooksOnline::status(),
            'years' => (int) config('quickbooks.years'),
            'cutoff' => (new Importer)->cutoff(),
            'batches' => MigrationBatch::query()->with('runner')->latest()->take(40)->get(),
            'counts' => $counts,
            'samples' => array_keys(Importer::KINDS),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Importer::KINDS))],
            'file' => ['required', 'file', 'mimes:csv,txt,tsv', 'max:51200'],
            'commit' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('file');
        $stored = $file->storeAs(config('quickbooks.upload_dir', 'migrations'), now()->format('Ymd-His').'-'.$data['kind'].'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()), 'local');

        $batch = RunImport::run($data['kind'], Storage::disk('local')->path((string) $stored), ! ($data['commit'] ?? false), $this->actor($request), $file->getClientOriginalName(), (string) $stored);

        return redirect()->route('admin.migration')->with('status', $this->summary($batch))->with('batch', $batch->id);
    }

    public function commit(Request $request, MigrationBatch $batch): RedirectResponse
    {
        $done = RunImport::commitBatch($batch, $this->actor($request));

        return redirect()->route('admin.migration')->with('status', $this->summary($done))->with('batch', $done->id);
    }

    /** The "summary of records that could not be imported" as a CSV (SOW §4). */
    public function issues(MigrationBatch $batch): Response
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            abort(500);
        }
        fputcsv($out, ['Row', 'Level', 'Field', 'Reason', 'Record'], ',', '"', '\\');
        foreach ($batch->issues ?? [] as $i) {
            fputcsv($out, [$i['row'], $i['level'], $i['field'] ?? '', $i['reason'], $i['record']], ',', '"', '\\');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="import-'.$batch->id.'-'.$batch->kind.'-issues.csv"']);
    }

    public function sample(string $kind): BinaryFileResponse
    {
        abort_unless(array_key_exists($kind, Importer::KINDS), 404);

        return response()->download(database_path("migration-samples/{$kind}.csv"), "sample-{$kind}.csv");
    }

    private function summary(MigrationBatch $b): string
    {
        if ($b->status === 'failed') {
            return 'Import failed: '.$b->error;
        }
        $what = $b->isDryRun() ? 'Dry run' : 'Imported';

        return "{$what} {$b->kindLabel()} — {$b->total} rows: {$b->imported} new, {$b->updated} updated, {$b->skipped} skipped, {$b->warnings} warnings.".($b->isDryRun() ? ' Nothing was written — review the issues, then commit.' : '');
    }

    private function actor(Request $request): User
    {
        /** @var User $u */
        $u = $request->user();

        return $u;
    }
}
