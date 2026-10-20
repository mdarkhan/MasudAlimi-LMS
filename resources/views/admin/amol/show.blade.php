<x-layout.admin :title="__('amol.heading')" :heading="$student->name">
    <div class="flex items-center gap-3 mb-4">
        <x-ui.button :href="route('admin.amol.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('messages.back_to_inbox') }}
        </x-ui.button>
        @if ($student->roll)
            <span class="text-sm text-muted tabular-nums">{{ bn($student->roll) }}</span>
        @endif
    </div>

    <x-amol.summary-strip :stats="$stats" />

    <x-amol.date-nav :date="$date" :today="$today" route="admin.amol.show" :route-params="['student' => $student]" />

    @php $parts = \App\Models\Amol::partition($checklist); @endphp

    <x-ui.card>
        <div class="flex items-center gap-3 mb-4">
            <h3 class="font-bold text-ink whitespace-nowrap">{{ date_label($date) }}</h3>
            <span class="flex-1 min-w-0 text-center text-sm font-semibold text-muted tabular-nums">
                {{ __('amol.progress', ['done' => bn($checklist->where('is_done', true)->count()), 'total' => bn($checklist->count())]) }}
            </span>
            <x-amol.expand-all />
        </div>

        {{-- Always read-only here: only the student's own screen may check an item off,
             and only for today (AmolService::toggle). The ustaz side never mutates it. --}}
        <div class="space-y-2 mb-2">
            @foreach ($parts['grouped'] as $group => $items)
                @continue($items->isEmpty())
                <x-amol.group-row :group="$group" :items="$items" :editable="false" />
            @endforeach
        </div>

        @if ($parts['ungrouped']->isNotEmpty())
            <x-amol.group-row group="daily" :items="$parts['ungrouped']" :editable="false" />
        @endif
    </x-ui.card>

    @php $hasNote = $note && $note->note !== ''; @endphp
    {{-- With a saved comment the card READS like a comment (text, who/when, Edit/Delete); the editor
         opens only on "Edit" — or straight away when there is nothing yet or the save failed validation. --}}
    <x-ui.card class="mt-5" x-data="{ editing: {{ $hasNote && ! $errors->has('note') ? 'false' : 'true' }} }">
        <h3 class="font-bold text-ink mb-3">{{ __('amol.note_heading') }}</h3>

        @if ($hasNote)
            <div x-show="!editing" x-cloak>
                <p class="text-sm text-ink leading-relaxed whitespace-pre-wrap [&_strong]:font-extrabold rounded-xl bg-surface-raised border border-line px-4 py-3">{!! chat_format($note->note) !!}</p>
                <p class="text-xs text-muted mt-2">
                    {{ __('amol.note_by', ['name' => $note->commentedBy?->name ?? '—', 'at' => dt_label($note->commented_at) ?? '']) }}
                </p>

                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" @click="editing = true; $nextTick(() => $refs.noteBox.focus())">
                        <x-ui.icon name="edit" class="w-4 h-4" /> {{ __('amol.edit_note_btn') }}
                    </x-ui.button>
                    <form method="POST" action="{{ route('admin.amol.note.destroy', $student) }}" data-confirm="{{ __('amol.confirm_delete_note') }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border border-rose-500/50 text-rose-600 bg-card hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-colors">
                            <x-ui.icon name="trash" class="w-4 h-4" /> {{ __('amol.delete_note') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.amol.note', $student) }}" x-show="editing" @if ($hasNote) x-cloak @endif>
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <x-ui.textarea x-ref="noteBox" name="note" rows="3" placeholder="{{ __('amol.note_placeholder') }}" title="{{ __('messages.bold_hint') }}" @keydown="window.richKeys($event, () => $el.form.requestSubmit())">{{ old('note', $note?->note) }}</x-ui.textarea>
            @error('note')<p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>@enderror

            <div class="mt-3 flex justify-end gap-2">
                @if ($hasNote)
                    <x-ui.button type="button" variant="secondary" @click="editing = false; $refs.noteBox.value = @js($note->note)">{{ __('ui.cancel') }}</x-ui.button>
                @endif
                <x-ui.button type="submit">{{ __('amol.save_note') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div id="amol-stats" class="mt-5 scroll-mt-20">
        <x-amol.stats :stats="$stats" route="admin.amol.show" :route-params="['student' => $student]" :analysis="true" />
    </div>
</x-layout.admin>
