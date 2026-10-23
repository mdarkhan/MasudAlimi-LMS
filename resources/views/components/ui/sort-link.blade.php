@props(['sort', 'current' => 'roll', 'dir' => 'asc', 'label', 'first' => 'asc'])

@php
    $active = $current === $sort;
    // Clicking the active column flips direction; a new column starts in its natural direction
    // (`first`: ascending by default, descending for things like scores where highest-first is expected).
    $next = $active ? ($dir === 'asc' ? 'desc' : 'asc') : $first;
    // Preserve search/status filters; reset pagination.
    $params = array_merge(request()->except('page'), ['sort' => $sort, 'dir' => $next]);
@endphp

<a href="?{{ http_build_query($params) }}" aria-label="{{ $label }}{{ $active ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' }}" title="{{ __('ui.sort_hint') }}"
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 hover:text-ink transition-colors '.($active ? 'text-ink font-semibold' : '')]) }}>
    {{ $label }}
    @if ($active)
        <span class="text-brand text-xs leading-none">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
    @else
        <span class="text-muted/30 text-xs leading-none">↕</span>
    @endif
</a>
