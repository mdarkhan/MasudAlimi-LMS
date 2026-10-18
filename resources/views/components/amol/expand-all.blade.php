{{-- One button that opens every prayer/daily dropdown at once, or closes them all. Plain DOM on the
     native <details> elements (no JS state to drift): the label always reflects what a click will do,
     and follows along when the user opens or closes a single dropdown by hand. --}}
<button type="button" data-amol-toggle-all data-open-label="{{ __('amol.expand_all') }}" data-close-label="{{ __('amol.collapse_all') }}"
        class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-line text-muted bg-card hover:text-brand hover:border-brand/50 transition-colors">
    {{-- Two different icons: arrows pointing apart = open everything, arrows pointing together = close everything. --}}
    <svg class="w-4 h-4 amol-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg>
    <svg class="w-4 h-4 amol-icon-close hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 20 5-5 5 5M7 4l5 5 5-5"/></svg>
    <span class="amol-toggle-label">{{ __('amol.expand_all') }}</span>
</button>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.querySelector('[data-amol-toggle-all]');
            if (!btn) return;
            var groups = function () { return Array.prototype.slice.call(document.querySelectorAll('details.amol-group')); };
            var sync = function () {
                var anyClosed = groups().some(function (d) { return !d.open; });
                btn.querySelector('.amol-toggle-label').textContent = anyClosed ? btn.dataset.openLabel : btn.dataset.closeLabel;
                btn.querySelector('.amol-icon-open').classList.toggle('hidden', !anyClosed);
                btn.querySelector('.amol-icon-close').classList.toggle('hidden', anyClosed);
            };
            btn.addEventListener('click', function () {
                var open = groups().some(function (d) { return !d.open; });
                groups().forEach(function (d) { d.open = open; });
                sync();
            });
            groups().forEach(function (d) { d.addEventListener('toggle', sync); });
            sync();
        });
    </script>
@endonce
