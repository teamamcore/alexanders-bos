<?php

declare(strict_types=1);

namespace App\Platform\Migration;

use App\Models\Customer;
use App\Models\MigrationBatch;
use App\Models\MigrationRecord;
use App\Models\User;
use App\Platform\Migration\Kinds\Customers;
use App\Platform\Migration\Kinds\Items;
use App\Platform\Migration\Kinds\Kind;
use App\Platform\Migration\Kinds\Receivables;
use App\Platform\Migration\Kinds\WorkOrders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * SOW §4 — runs one file through one Kind, counting imported / updated /
 * skipped and keeping the reasons ("summary of any records that could not
 * be imported"). Dry runs do the full pass inside a transaction and roll it
 * back, so the report is exactly what a commit would do.
 */
final class Importer
{
    public const array KINDS = [
        'customers' => 'Customers',
        'items' => 'Items (products & services)',
        'receivables' => 'Invoices / open A/R',
        'work_orders' => 'Work order records',
    ];

    /** @var list<array{row: int, level: string, field: string|null, reason: string, record: string}> */
    private array $issues = [];

    private int $warnings = 0;

    private int $currentRow = 0;

    private string $currentRecord = '';

    private ?MigrationBatch $batch = null;

    private readonly Carbon $cutoff;

    public function __construct()
    {
        $this->cutoff = today()->subYears(max(1, (int) config('quickbooks.years', 8)))->startOfDay();
    }

    public static function kind(string $kind): Kind
    {
        return match ($kind) {
            'customers' => new Customers,
            'items' => new Items,
            'receivables' => new Receivables,
            'work_orders' => new WorkOrders,
            default => throw new \InvalidArgumentException("Unknown import kind '{$kind}'."),
        };
    }

    public function cutoff(): Carbon
    {
        return $this->cutoff;
    }

    public function run(string $kind, string $path, bool $dryRun, ?User $runner, ?string $fileName = null, ?string $storedPath = null): MigrationBatch
    {
        $k = self::kind($kind);
        $batch = MigrationBatch::create([
            'run_by' => $runner?->id,
            'kind' => $kind,
            'source' => 'csv',
            'file_name' => $fileName ?? basename($path),
            'file_path' => $storedPath,
            'mode' => $dryRun ? 'dry_run' : 'commit',
            'status' => 'done',
            'started_at' => now(),
        ]);
        $this->batch = $batch;

        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $total = 0;
        $mapping = [];

        try {
            $file = new CsvFile($path, $k->aliases());
            $mapping = $file->mapping();
            $missing = array_diff($k->required(), array_values($mapping));
            if ($missing !== []) {
                throw new \RuntimeException('Missing required column(s): '.implode(', ', array_map(fn ($f) => $k->aliases()[$f][0], $missing)).'. Found: '.implode(', ', $file->headers()).'.');
            }

            DB::beginTransaction();
            try {
                foreach ($file->rows() as $row) {
                    $total++;
                    $this->currentRow = (int) $row['_row'];
                    $this->currentRecord = Str::limit(implode(' · ', array_filter(array_slice(array_values(array_diff_key($row, ['_row' => 1])), 0, 3))), 80);

                    $dateField = $k->dateField();
                    if ($dateField !== null && ($row[$dateField] ?? '') !== '') {
                        $d = self::date($row[$dateField]);
                        if (! $d instanceof Carbon) {
                            $this->skip($dateField, "Unreadable date '{$row[$dateField]}'.");
                            $counts['skipped']++;

                            continue;
                        }
                        if ($d->lt($this->cutoff)) {
                            $this->skip($dateField, 'Older than the '.config('quickbooks.years').'-year import window (before '.$this->cutoff->toDateString().').');
                            $counts['skipped']++;

                            continue;
                        }
                    }

                    try {
                        $outcome = $k->import($row, $this);
                    } catch (Throwable $e) {
                        $this->skip(null, 'Error: '.Str::limit($e->getMessage(), 160));
                        $outcome = 'skipped';
                    }
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                }
                $dryRun ? DB::rollBack() : DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            $batch->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);
        }

        $batch->forceFill([
            'total' => $total,
            'imported' => $counts['imported'],
            'updated' => $counts['updated'],
            'skipped' => $counts['skipped'],
            'warnings' => $this->warnings,
            'issues' => array_slice($this->issues, 0, (int) config('quickbooks.max_issues', 500)),
            'columns' => $mapping,
            'finished_at' => now(),
        ])->save();

        return $batch;
    }

    // ---- helpers for Kinds ---------------------------------------------------

    public function skip(?string $field, string $reason): void
    {
        $this->issues[] = ['row' => $this->currentRow, 'level' => 'skipped', 'field' => $field, 'reason' => $reason, 'record' => $this->currentRecord];
    }

    public function warn(?string $field, string $reason): void
    {
        $this->warnings++;
        $this->issues[] = ['row' => $this->currentRow, 'level' => 'warning', 'field' => $field, 'reason' => $reason, 'record' => $this->currentRecord];
    }

    /** Previously imported model for this source key, if any. */
    public function existing(string $kind, string $sourceKey): ?Model
    {
        $rec = MigrationRecord::query()->where('kind', $kind)->where('source_key', $sourceKey)->first();

        return $rec?->model;
    }

    /** @param  array<string, string>  $raw */
    public function remember(string $kind, string $sourceKey, Model $model, array $raw): void
    {
        MigrationRecord::query()->updateOrCreate(
            ['kind' => $kind, 'source_key' => $sourceKey],
            ['batch_id' => $this->batch?->id, 'model_type' => $model->getMorphClass(), 'model_id' => $model->getKey(), 'raw' => array_diff_key($raw, ['_row' => 1])],
        );
    }

    /**
     * Customer by QuickBooks name ("Parent:Sub-customer" → parent). Creates a
     * placeholder when missing (warned) so the receivable / work order still lands.
     *
     * @return array{0: Customer, 1: string|null} customer, sub-customer (job/site) name
     */
    public function customerFor(string $qbName, bool $createIfMissing = true): array
    {
        $parts = array_map(trim(...), explode(':', $qbName, 2));
        $name = $parts[0];
        $sub = $parts[1] ?? null;
        if ($name === '') {
            throw new \RuntimeException('Customer name is blank.');
        }

        $customer = $this->existing('customers', self::key($name));
        if (! $customer instanceof Customer) {
            $customer = Customer::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        }
        if (! $customer instanceof Customer) {
            if (! $createIfMissing) {
                throw new \RuntimeException("Customer '{$name}' not found.");
            }
            $customer = Customer::create(['name' => $name, 'payment_terms' => 'net_30', 'notes' => 'Created by QuickBooks import — details pending.']);
            $this->remember('customers', self::key($name), $customer, ['name' => $name]);
            $this->warn('customer', "Customer '{$name}' was not in the system; created a placeholder (import the customer list to fill it in).");
        }

        return [$customer, $sub !== '' ? $sub : null];
    }

    public static function key(string $s): string
    {
        return Str::slug(mb_strtolower(trim($s))) ?: md5($s);
    }

    public static function money(?string $s): ?float
    {
        if ($s === null || trim($s) === '') {
            return null;
        }
        $neg = str_contains($s, '(') || str_starts_with(trim($s), '-');
        $n = preg_replace('/[^0-9.]/', '', $s) ?? '';
        if ($n === '' || ! is_numeric($n)) {
            return null;
        }

        return round((float) $n * ($neg ? -1 : 1), 2);
    }

    public static function date(?string $s): ?Carbon
    {
        if ($s === null || trim($s) === '') {
            return null;
        }
        try {
            return Carbon::parse(trim($s))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function terms(?string $s): ?string
    {
        $t = mb_strtolower(trim((string) $s));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'receipt') || $t === 'cod') {
            return 'due_on_receipt';
        }
        if (preg_match('/net\s*(\d+)/', $t, $m)) {
            $days = (int) $m[1];
            $best = null;
            foreach (config('batsons.payment_terms', []) as $key => $d) {
                if ($best === null || abs($d - $days) < abs(config('batsons.payment_terms')[$best] - $days)) {
                    $best = $key;
                }
            }

            return $best;
        }

        return null;
    }
}
