<?php

namespace App\Services\Amol;

use App\Models\Amol;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only consistency numbers for the daily amol tracker: streaks and a month view.
 * Derived on demand from amol_entries — nothing is cached or stored, so it can never
 * drift from the checkmarks themselves.
 *
 * Two streaks, because "every single deed, every day" is a high bar:
 *  - full   : consecutive days on which EVERY active deed was done,
 *  - active : consecutive days on which at least one deed was done.
 * Today is still in progress, so an unfinished today never breaks a running streak —
 * it simply is not counted yet.
 */
class AmolStatsService
{
    /**
     * @return array{
     *   total: int, current_full: int, best_full: int, current_active: int, best_active: int,
     *   month: CarbonImmutable, days: list<array{date: CarbonImmutable, done: int, percent: int, future: bool, is_today: bool}>,
     *   month_full_days: int, month_average: int
     * }
     */
    public function forStudent(User $student, ?CarbonImmutable $month = null, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $month = ($month ?? $today)->startOfMonth();
        $total = Amol::query()->active()->count();

        /** @var array<string, int> $perDay date => number of active deeds done */
        $perDay = DB::table('amol_entries')
            ->join('amols', 'amols.id', '=', 'amol_entries.amol_id')
            ->where('amol_entries.user_id', $student->getKey())
            ->where('amol_entries.is_done', true)
            ->where('amols.is_active', true)
            ->groupBy('amol_entries.date')
            ->selectRaw('amol_entries.date as d, count(*) as c')
            ->pluck('c', 'd')
            ->map(fn ($c) => (int) $c)
            ->all();

        $isFull = fn (int $c) => $total > 0 && $c >= $total;
        $isActive = fn (int $c) => $c > 0;

        $days = [];
        $fullDays = 0;
        $sum = 0;
        $elapsed = 0;
        for ($d = $month; $d->month === $month->month; $d = $d->addDay()) {
            $done = $perDay[$d->toDateString()] ?? 0;
            $future = $d->greaterThan($today);
            $days[] = [
                'date' => $d,
                'done' => $done,
                'percent' => $total > 0 ? (int) round($done * 100 / $total) : 0,
                'future' => $future,
                'is_today' => $d->equalTo($today),
            ];
            if (! $future) {
                $elapsed++;
                $sum += $total > 0 ? $done / $total : 0;
                $fullDays += $isFull($done) ? 1 : 0;
            }
        }

        $avg = function (CarbonImmutable $from, int $count) use ($perDay, $total): int {
            if ($total === 0 || $count === 0) {
                return 0;
            }
            $sum = 0;
            for ($i = 0; $i < $count; $i++) {
                $sum += ($perDay[$from->addDays($i)->toDateString()] ?? 0) / $total;
            }

            return (int) round($sum * 100 / $count);
        };

        // Rolling 7-day windows ending today, and the whole previous calendar month — the
        // "how is this student trending" comparisons the ustaz's analysis view needs.
        $prevMonth = $month->subMonth()->startOfMonth();
        $hasPrev = collect($perDay)->keys()->contains(fn (string $d) => $d >= $prevMonth->toDateString() && $d < $month->toDateString());

        // Per-deed consistency for the viewed month (days it was ticked / days elapsed).
        $monthEnd = $month->endOfMonth()->startOfDay();
        $upTo = $today->lessThan($monthEnd) ? $today : $monthEnd;
        $deeds = [];
        if ($elapsed > 0) {
            $ticked = DB::table('amol_entries')
                ->where('user_id', $student->getKey())->where('is_done', true)
                ->whereBetween('date', [$month->toDateString(), $upTo->toDateString()])
                ->groupBy('amol_id')->selectRaw('amol_id, count(*) as c')->pluck('c', 'amol_id');

            foreach (Amol::query()->active()->ordered()->get(['id', 'label', 'group']) as $amol) {
                $n = (int) ($ticked[$amol->id] ?? 0);
                $deeds[] = ['label' => $amol->label, 'group' => $amol->group, 'days' => $n, 'percent' => (int) round($n * 100 / $elapsed)];
            }
            usort($deeds, fn ($x, $y) => $x['percent'] <=> $y['percent']);
        }

        return [
            'elapsed' => $elapsed,
            'week_average' => $avg($today->subDays(6), 7),
            'prev_week_average' => $avg($today->subDays(13), 7),
            'prev_month_average' => $hasPrev ? $avg($prevMonth, $prevMonth->daysInMonth) : null,
            'deeds' => $deeds,
            'total' => $total,
            'current_full' => $this->current($perDay, $isFull, $today),
            'best_full' => $this->best($perDay, $isFull),
            'current_active' => $this->current($perDay, $isActive, $today),
            'best_active' => $this->best($perDay, $isActive),
            'month' => $month,
            'days' => $days,
            'month_full_days' => $fullDays,
            'month_average' => $elapsed > 0 ? (int) round($sum * 100 / $elapsed) : 0,
        ];
    }

    /** Run of qualifying days ending today (or yesterday, if today is not yet qualifying). */
    private function current(array $perDay, callable $qualifies, CarbonImmutable $today): int
    {
        $cursor = $qualifies($perDay[$today->toDateString()] ?? 0) ? $today : $today->subDay();
        $run = 0;
        while ($qualifies($perDay[$cursor->toDateString()] ?? 0)) {
            $run++;
            $cursor = $cursor->subDay();
        }

        return $run;
    }

    private function best(array $perDay, callable $qualifies): int
    {
        $dates = array_keys(array_filter($perDay, $qualifies));
        sort($dates);

        $best = $run = 0;
        $prev = null;
        foreach ($dates as $date) {
            $run = ($prev !== null && CarbonImmutable::parse($prev)->addDay()->toDateString() === $date) ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $date;
        }

        return $best;
    }
}
