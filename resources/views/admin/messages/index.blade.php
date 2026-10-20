<x-layout.admin :title="__('messages.inbox_heading')" :heading="__('messages.inbox_heading')">

    {{-- The ustaz opening a thread with a student who has not written in yet. --}}
    @if ($students->isNotEmpty())
        <x-ui.card class="mb-6">
            <form method="POST" action="{{ route('admin.messages.start') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                @csrf
                <label class="flex-1 min-w-0">
                    <span class="mb-1 block text-xs font-semibold text-muted">{{ __('messages.start_new') }}</span>
                    <x-ui.select name="student_id" required>
                        <option value="">{{ __('messages.pick_student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->roll ? bn($student->roll).' — ' : '' }}{{ $student->name }}</option>
                        @endforeach
                    </x-ui.select>
                </label>
                <x-ui.button type="submit" variant="secondary">
                    <x-ui.icon name="message" class="w-4 h-4" /> {{ __('messages.start') }}
                </x-ui.button>
            </form>
        </x-ui.card>
    @endif

    @if ($conversations->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('messages.empty_inbox')">{{ __('messages.empty_inbox_hint') }}</x-ui.empty>
        </x-ui.card>
    @else
        <div class="space-y-2">
            @foreach ($conversations as $conversation)
                <a href="{{ route('admin.messages.show', $conversation) }}"
                   @class([
                       'flex items-center gap-4 p-4 rounded-2xl border transition-colors hover:border-brand/50',
                       'border-brand/40 bg-brand-tint/40' => $conversation->unread_count > 0,
                       'border-line bg-surface' => $conversation->unread_count === 0,
                   ])>
                    <span class="shrink-0 grid place-items-center size-10 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ mb_strlen((string) $conversation->student->roll) > 3 ? 'text-xs' : 'text-base' }}"
                          title="{{ __('dashboard.roll') }}">
                        {{ $conversation->student->roll ? bn($conversation->student->roll) : '—' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate pl-1.5 -ml-1.5">
                            <span class="font-semibold text-ink">{{ $conversation->student->name }}</span>
                            @if ($conversation->student->guardian_name)
                                <span class="text-sm text-muted">· {{ $conversation->student->guardian_name }}</span>
                            @endif
                        </p>
                        <p class="text-sm text-muted truncate mt-0.5 pl-1.5 -ml-1.5">{{ $conversation->latestMessage?->body ?? '—' }}</p>
                    </div>
                    <div class="shrink-0 text-end">
                        @if ($conversation->unread_count > 0)
                            <x-ui.badge color="brand">{{ bn($conversation->unread_count) }} {{ __('messages.unread_badge') }}</x-ui.badge>
                        @endif
                        <p class="text-xs text-muted tabular-nums mt-1 whitespace-nowrap">
                            {{ dt_label($conversation->last_message_at) ?? '—' }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layout.admin>
