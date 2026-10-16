<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MSA Exhibit B.2 — reject obvious Prohibited Data before it is ever persisted:
 * payment card / bank numbers and SSNs / government IDs. This is a guardrail,
 * not a guarantee; Client remains responsible for what it submits.
 */
class NoProhibitedData implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        $digits = preg_replace('/\D/', '', $value);

        // 13–19 digit sequences passing Luhn look like card/bank numbers.
        if ($digits && strlen($digits) >= 13 && strlen($digits) <= 19 && $this->luhn($digits)) {
            $fail('This field may not contain payment card or bank account numbers (MSA Exhibit B.2).');
        }
        // US SSN pattern.
        if (preg_match('/\b\d{3}-\d{2}-\d{4}\b/', $value)) {
            $fail('This field may not contain Social Security or government ID numbers (MSA Exhibit B.2).');
        }
    }

    private function luhn(string $number): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
