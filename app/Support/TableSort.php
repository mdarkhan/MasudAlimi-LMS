<?php

namespace App\Support;

use App\Services\Import\BengaliText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Click-to-sort table columns, done on the SERVER so a sort covers every page of a paginated list — not
 * just the rows the browser happens to have. Pair with <x-ui.sort-link> in the header.
 *
 *   $sort = TableSort::from($request, ['title', 'date'], default: 'date', dir: 'desc');
 *   $sort->query($builder, ['title' => 'title', 'date' => 'created_at']);         // SQL lists
 *   $sort->collection($rows, ['title' => fn ($r) => $r->title, ...]);            // in-memory lists
 *
 * Collection sorting is what makes text columns come out right: Bengali and Latin digits are compared as
 * numbers ("সীরাত-৯" before "সীরাত-১০", which plain SQL ordering gets backwards), empty values always go
 * last whichever direction is chosen, and ties keep their previous (default) order — the sort is stable.
 * Unknown `sort`/`dir` values from the URL are ignored, never passed to SQL.
 */
final class TableSort
{
    private function __construct(public readonly string $key, public readonly string $dir) {}

    /**
     * @param  list<string>  $keys
     * @param  array<string, 'asc'|'desc'>  $natural  the direction a column starts in when no `dir` is given
     *                                              (e.g. scores highest-first)
     */
    public static function from(Request $request, array $keys, string $default, string $dir = 'asc', array $natural = []): self
    {
        $key = (string) $request->query('sort', $default);
        $wanted = strtolower((string) $request->query('dir', ''));

        if (! in_array($key, $keys, true)) {
            return new self($default, $dir);
        }
        $start = $natural[$key] ?? ($key === $default ? $dir : 'asc');

        return new self($key, in_array($wanted, ['asc', 'desc'], true) ? $wanted : $start);
    }

    public function desc(): bool
    {
        return $this->dir === 'desc';
    }

    /**
     * @param  array<string, string|\Closure(Builder, string): mixed>  $map  key => column, or a closure that orders the builder
     */
    public function query(Builder $query, array $map): Builder
    {
        $how = $map[$this->key] ?? null;
        if ($how instanceof \Closure) {
            $how($query, $this->dir);
        } elseif (is_string($how)) {
            // NULLs last in both directions (MySQL puts them first when ascending)
            $query->orderByRaw($query->getQuery()->getGrammar()->wrap($how).' IS NULL')->orderBy($how, $this->dir);
        }

        return $query;
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $rows
     * @param  array<string, \Closure(T): mixed>  $map
     * @return Collection<int, T>
     */
    public function collection(Collection $rows, array $map): Collection
    {
        $value = $map[$this->key] ?? null;
        if (! $value) {
            return $rows->values();
        }

        $indexed = $rows->values()->map(fn ($row, $i) => [$i, $value($row), $row])->all();
        usort($indexed, function ($a, $b) {
            [$ia, $va] = $a;
            [$ib, $vb] = $b;
            $na = $va === null || $va === '';
            $nb = $vb === null || $vb === '';
            if ($na || $nb) {
                return $na === $nb ? $ia <=> $ib : ($na ? 1 : -1);   // blanks last, always
            }
            $cmp = self::compare($va, $vb);
            if ($this->desc()) {
                $cmp = -$cmp;
            }

            return $cmp ?: $ia <=> $ib;   // stable
        });

        return collect(array_column($indexed, 2));
    }

    /** Sort a full collection, then cut the requested page — for lists that are built in PHP. */
    public function paginate(Collection $rows, array $map, Request $request, int $perPage): LengthAwarePaginator
    {
        $sorted = $this->collection($rows, $map);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    public static function compare(mixed $a, mixed $b): int
    {
        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return $a->getTimestamp() <=> $b->getTimestamp();
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }
        if (is_bool($a) || is_bool($b)) {
            return (int) $a <=> (int) $b;
        }

        // Natural, case-insensitive text order with Bengali digits read as numbers.
        return strnatcasecmp(BengaliText::toLatinDigits((string) $a), BengaliText::toLatinDigits((string) $b));
    }
}
