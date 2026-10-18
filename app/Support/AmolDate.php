<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Resolves a `?date=` query param into a safe calendar date: never trusts the string
 * blindly, and clamps anything in the future back to today (viewing "tomorrow's"
 * amolnama makes no sense — there is nothing there and there never will be before it
 * arrives). Used by both the student and admin amol screens.
 */
class AmolDate
{
    /** `?month=YYYY-MM` as the first day of a real month, never later than the current one. */
    public static function resolveMonth(mixed $raw): CarbonImmutable
    {
        $current = CarbonImmutable::now()->startOfMonth();

        if (! is_string($raw) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw)) {
            return $current;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $raw);

        return $month->greaterThan($current) ? $current : $month;
    }

    public static function resolve(?string $raw): CarbonImmutable
    {
        $today = CarbonImmutable::now()->startOfDay();

        if ($raw === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $today;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();

        return $date->greaterThan($today) ? $today : $date;
    }
}
