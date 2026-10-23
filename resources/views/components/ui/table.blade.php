@props(['head' => null, 'sortable' => false])   {{-- sortable: click-to-sort headers (app.js); for short, unpaginated lists --}}
<div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left" @if ($sortable) data-sortable data-sort-hint="{{ __('ui.sort_hint') }}" @endif>
            @if ($head)
                <thead class="bg-ink/[0.03] border-b border-line text-xs uppercase tracking-wider text-muted">
                    <tr>{{ $head }}</tr>
                </thead>
            @endif
            <tbody class="divide-y divide-line">{{ $slot }}</tbody>
        </table>
    </div>
</div>
