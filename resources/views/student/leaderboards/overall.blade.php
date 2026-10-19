<x-layout.student :title="__('leaderboards.overall_heading')" :heading="__('leaderboards.overall_heading')">
    <x-leaderboards.picker :quizzes="$quizzes" :active-quiz-id="null" />

    <div class="mb-3 flex items-center justify-between gap-3">
        @if ($me)
            {{-- Jumps to the page (and the row) where you are — your row is highlighted there. --}}
            <a href="{{ request()->fullUrlWithQuery(['page' => $myPage ?? 1]) }}#me" data-jump-me
               class="inline-flex items-center gap-2 rounded-xl bg-brand-tint px-4 py-2 text-lg font-bold text-brand-strong ring-2 ring-brand/30 hover:ring-brand transition">
                {{ __('leaderboards.your_rank', ['rank' => bn($me['rank'])]) }}
                <x-ui.icon name="chevron" class="w-4 h-4" />
            </a>
        @else
            <span></span>
        @endif
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink print:hidden">
            <x-ui.icon name="download" class="w-4 h-4" /> {{ __('leaderboards.print') }}
        </button>
    </div>

    <x-leaderboards.table :rows="$rows" :me="$me" />
</x-layout.student>
