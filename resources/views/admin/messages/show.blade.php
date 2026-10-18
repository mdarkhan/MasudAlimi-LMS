<x-layout.admin :title="__('messages.thread_heading')" :heading="$conversation->student->name">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 min-w-0">
            <x-ui.button :href="route('admin.messages.index')" variant="secondary">
                <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('messages.back_to_inbox') }}
            </x-ui.button>
            @if ($conversation->student->roll)
                <span class="text-sm text-muted tabular-nums">{{ bn($conversation->student->roll) }}</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.messages.destroy', $conversation) }}"
              data-confirm="{{ __('messages.confirm_delete') }}">
            @csrf @method('DELETE')
            <button type="submit" class="inline-flex items-center justify-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border border-rose-500/50 text-rose-600 bg-card hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-colors">
                <x-ui.icon name="trash" class="w-4 h-4" /> {{ __('messages.delete_thread') }}
            </button>
        </form>
    </div>

    <x-messages.thread
        :messages="$messages"
        :poll-url="route('admin.messages.poll', $conversation)"
        :send-url="route('admin.messages.store', $conversation)"
        :edit-url="route('admin.messages.update', ['conversation' => $conversation, 'message' => '__ID__'])"
        :delete-url="route('admin.messages.message.destroy', ['conversation' => $conversation, 'message' => '__ID__'])" />
</x-layout.admin>
