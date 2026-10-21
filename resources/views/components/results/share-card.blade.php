@props(['attempt', 'quiz', 'rank' => null, 'percentage' => 0])
{{-- A shareable picture of a RELEASED result (the caller has already release-gated the page). Drawn in the
     browser (resources/js/share-card.js); nothing is uploaded or stored. Only what the result page itself
     shows goes on it: exam, score, percentage, rank, the student's own name and roll. --}}
@php
    $card = [
        'brand' => 'মাসউদ আলিমী',
        'title' => $quiz->title,
        'course' => $quiz->course?->title,
        'scoreLabel' => __('results.card_score_label'),
        'score' => bn($attempt->final_score),
        'total' => bn($attempt->total_marks_snapshot),
        'rank' => $rank ? __('results.card_rank', ['rank' => bn($rank)]) : null,
        'name' => $attempt->user->name,
        'roll' => __('results.card_roll', ['roll' => bn($attempt->user->roll)]),
        'footer' => date_label($attempt->submitted_at),
        // always the main site's address, whichever host the card was made on
        'site' => config('app.public_site'),
    ];
    $file = 'result-'.\App\Support\Slug::make($quiz->title).'.jpg';
@endphp

{{-- x-data lives on a plain <div>, NOT on <x-ui.card>: Blade does not compile @js() inside a component
     tag's attributes, so the data arrived as literal text and the button silently did nothing. --}}
<x-ui.card {{ $attributes->merge(['class' => 'print:hidden']) }}>
    <div x-data="{
        card: @js($card), url: null, blob: null, busy: false, canShare: false, hint: false,
        async make() {
            this.busy = true;
            try {
                const render = await window.loadShareCard();
                this.blob = await render(this.card);
                this.url = URL.createObjectURL(this.blob);
                const file = new File([this.blob], @js($file), { type: 'image/jpeg' });
                this.canShare = !! (navigator.canShare && navigator.canShare({ files: [file] }));
            } finally { this.busy = false; }
        },
        // Phones: the system share sheet (WhatsApp, Messenger, gallery, …). Where a browser cannot share
        // a picture (most desktops) the picture is saved instead, with a hint to send it from there.
        async share() {
            const file = new File([this.blob], @js($file), { type: 'image/jpeg' });
            if (this.canShare) {
                try { await navigator.share({ files: [file], title: this.card.title, text: this.card.site }); } catch (e) {}
                return;
            }
            this.$refs.download.click();
            this.hint = true;
        },
    }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h3 class="font-bold text-ink flex items-center gap-2"><x-ui.icon name="share" class="w-4 h-4 text-brand" /> {{ __('results.share_heading') }}</h3>
            <p class="text-sm text-muted mt-0.5">{{ __('results.share_hint') }}</p>
        </div>
        <x-ui.button type="button" x-show="! url" @click="make()" ::disabled="busy">
            <span x-show="! busy">{{ __('results.share_make') }}</span>
            <span x-show="busy" x-cloak>{{ __('results.share_making') }}</span>
        </x-ui.button>
    </div>

    <template x-if="url">
        <div class="mt-4 flex flex-col sm:flex-row gap-4 sm:items-end">
            <img :src="url" alt="{{ __('results.share_preview_alt') }}" class="w-full sm:w-64 rounded-xl border border-line shadow-sm aspect-[4/5] object-cover">
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" @click="share()"><x-ui.icon name="share" class="w-4 h-4" /> {{ __('results.share_button') }}</x-ui.button>
                {{-- same look as the share button (x-ui.button primary); a plain <a> because it needs :href + download --}}
                <a x-ref="download" :href="url" download="{{ $file }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand text-brand-ink hover:bg-brand-strong shadow-sm px-4 py-2.5 text-sm font-semibold transition-colors">
                    <x-ui.icon name="image-down" class="w-4 h-4" /> {{ __('results.share_download') }}
                </a>
                <p x-show="hint" x-cloak class="basis-full text-xs text-muted">{{ __('results.share_saved_hint') }}</p>
            </div>
        </div>
    </template>
    </div>
</x-ui.card>
