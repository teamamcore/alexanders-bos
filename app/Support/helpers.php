<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Str;

if (! function_exists('term')) {
    /**
     * SOW §3.13 — tenant-configurable terminology.
     * term('work_order') → "Work Order" by default, or whatever the client calls it.
     */
    function term(string $key, bool $plural = false): string
    {
        $label = Tenant::current()?->term($key)
            ?? config("batsons.terminology.{$key}", Str::headline($key));

        return $plural ? Str::plural($label) : $label;
    }
}

if (! function_exists('abbr')) {
    /** SOW §3.13 — tenant-configurable abbreviation. abbr('work_order') → "WO". */
    function abbr(string $key): string
    {
        return Tenant::current()?->abbr($key)
            ?? config("batsons.abbreviations.{$key}", Str::upper(Str::substr($key, 0, 2)));
    }
}
