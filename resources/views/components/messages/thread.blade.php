@props([
    'messages' => [],     // MessagePresenter payload — the history, rendered server-side
    'pollUrl',            // GET ?after={id} → { messages: [...] }
    'sendUrl',            // POST { body } → { message: {...} }
    'deleteUrl' => null,  // DELETE template with __ID__ — staff may remove any message
    'editUrl' => null,    // PUT template with __ID__ — set only where the viewer may edit their own messages
])

{{-- Student ↔ ustaz chat.

     There is no WebSocket on this hosting (no queue worker, BROADCAST_CONNECTION=log),
     so the thread polls — the same approach the live exam screen uses. Ten seconds is
     short enough to feel live for a Q&A exchange and cheap enough for shared hosting.

     History is rendered server-side (so the page is complete and readable without JS);
     Alpine only appends what arrives while the page is open. Bodies are written by
     students and admins alike, so they are ALWAYS escaped — {{ }} here, x-text below —
     and never rendered as HTML or Markdown. --}}

<div x-data="messageThread({
        poll: @js($pollUrl),
        send: @js($sendUrl),
        edit: @js($editUrl),
        del: @js($deleteUrl),
        lastId: {{ collect($messages)->max('id') ?? 0 }},
        lastDay: @js(collect($messages)->last()['day'] ?? ''),
     })"
     class="flex flex-col rounded-2xl border border-line bg-surface overflow-hidden"
     style="height: min(70vh, 42rem)">

    <div x-ref="scroll" class="chat-wall flex-1 overflow-y-auto px-3 sm:px-5 py-4 space-y-1">
        @php $prevDay = null; @endphp
        @forelse ($messages as $message)
            @if ($message['day'] !== $prevDay)
                <div class="flex justify-center py-2"><span class="chat-day">{{ $message['day_label'] }}</span></div>
                @php $prevDay = $message['day']; @endphp
            @endif
            <div @class(['flex', 'justify-end' => $message['mine'], 'justify-start' => ! $message['mine']]) x-show="!removed[{{ $message['id'] }}]">
                <div @class(['chat-bubble', 'chat-mine' => $message['mine'], 'chat-theirs' => ! $message['mine']])>
                    @unless ($message['mine'])<span class="chat-author">{{ $message['author'] }}</span>@endunless
                    @if ($deleteUrl)
                        <button type="button" class="chat-edit-btn is-danger" @click="askDelete({{ $message['id'] }})" aria-label="{{ __('messages.delete_message') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                    @endif
                    @if ($editUrl && $message['mine'])
                        <button type="button" class="chat-edit-btn" @click="startEdit({{ $message['id'] }}, @js($message['body']))" aria-label="{{ __('messages.edit') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/></svg></button>
                    @endif
                    <span class="chat-text" x-show="editingId !== {{ $message['id'] }} && edits[{{ $message['id'] }}] === undefined">{!! chat_format($message['body']) !!}</span>
                    <span class="chat-text" x-show="editingId !== {{ $message['id'] }} && edits[{{ $message['id'] }}] !== undefined" x-html="fmt(edits[{{ $message['id'] }}])" x-cloak></span>
                    @if ($editUrl && $message['mine'])
                        <div x-show="editingId === {{ $message['id'] }}" x-cloak class="chat-editor">
                            <textarea x-model="editText" rows="2" @keydown="onKey($event, 'edit', {{ $message['id'] }})"></textarea>
                            <div class="chat-editor-actions"><button type="button" @click="cancelEdit()">{{ __('messages.cancel') }}</button><button type="button" class="is-primary" :disabled="saving || editText.trim() === ''" @click="saveEdit({{ $message['id'] }})">{{ __('messages.save') }}</button></div>
                            <p x-show="editError" x-text="editError" class="text-xs text-rose-600 mt-1"></p>
                        </div>
                    @endif
                    @if ($deleteUrl)
                        <div x-show="deletingId === {{ $message['id'] }}" x-cloak class="chat-editor">
                            <p class="text-sm font-semibold mb-1">{{ __('messages.confirm_delete_message') }}</p>
                            <div class="chat-editor-actions"><button type="button" @click="deletingId = null">{{ __('messages.cancel') }}</button><button type="button" class="is-danger-solid" :disabled="saving" @click="doDelete({{ $message['id'] }})">{{ __('messages.delete_message') }}</button></div>
                        </div>
                    @endif
                    <span class="chat-meta"><em x-show="{{ $message['edited'] ? 'true' : 'false' }} || edits[{{ $message['id'] }}] !== undefined" class="chat-edited">{{ __('messages.edited') }}</em>{{ $message['time'] }}@if ($message['mine'])<svg class="chat-tick {{ $message['read'] ? 'is-read' : '' }}" viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6.5l3.5 3.5L11 2.5M7 9.5l.9.9L15 2.5"/></svg>@endif</span>
                </div>
            </div>
        @empty
            <p x-show="incoming.length === 0" class="chat-day mx-auto w-fit my-8">{{ __('messages.empty_thread') }}</p>
        @endforelse

        {{-- Everything that arrives after the page was rendered. --}}
        <template x-for="m in incoming" :key="m.id">
            <div>
                <div x-show="m.showDay" class="flex justify-center py-2"><span class="chat-day" x-text="m.day_label"></span></div>
                <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                    <div class="chat-bubble" :class="m.mine ? 'chat-mine' : 'chat-theirs'">
                        <span x-show="!m.mine" class="chat-author" x-text="m.author"></span>
                        <button type="button" x-show="canDelete" x-cloak class="chat-edit-btn is-danger" @click="askDelete(m.id)" aria-label="{{ __('messages.delete_message') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>
                        <button type="button" x-show="m.mine && canEdit" x-cloak class="chat-edit-btn" @click="startEdit(m.id, m.body)" aria-label="{{ __('messages.edit') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/></svg></button>
                        <span class="chat-text" x-show="editingId !== m.id" x-html="fmt(m.body)"></span>
                        <div x-show="editingId === m.id" x-cloak class="chat-editor">
                            <textarea x-model="editText" rows="2" @keydown="onKey($event, 'edit', m.id)"></textarea>
                            <div class="chat-editor-actions"><button type="button" @click="cancelEdit()">{{ __('messages.cancel') }}</button><button type="button" class="is-primary" :disabled="saving || editText.trim() === ''" @click="saveEdit(m.id)">{{ __('messages.save') }}</button></div>
                            <p x-show="editError" x-text="editError" class="text-xs text-rose-600 mt-1"></p>
                        </div>
                        <div x-show="deletingId === m.id" x-cloak class="chat-editor">
                            <p class="text-sm font-semibold mb-1">{{ __('messages.confirm_delete_message') }}</p>
                            <div class="chat-editor-actions"><button type="button" @click="deletingId = null">{{ __('messages.cancel') }}</button><button type="button" class="is-danger-solid" :disabled="saving" @click="doDelete(m.id)">{{ __('messages.delete_message') }}</button></div>
                        </div>
                        <span class="chat-meta"><em x-show="m.edited" class="chat-edited">{{ __('messages.edited') }}</em><span x-text="m.time"></span><svg x-show="m.mine" class="chat-tick" :class="m.read ? 'is-read' : ''" viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6.5l3.5 3.5L11 2.5M7 9.5l.9.9L15 2.5"/></svg></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <form @submit.prevent="send()" class="chat-composer shrink-0 relative px-3 py-2.5" x-data="emojiPicker()">
        <p x-show="error" x-cloak class="mb-2 text-xs text-rose-600" x-text="error"></p>

        <div x-show="emojiOpen" x-cloak x-transition @click.outside="emojiOpen = false" @keydown.escape.window="emojiOpen = false"
             class="chat-emoji-panel absolute bottom-full left-2 mb-2 flex flex-col rounded-2xl shadow-2xl overflow-hidden">
            <div class="emoji-tabs flex items-center justify-between px-1 pt-1">
                <button type="button" class="emoji-tab" :class="tab === 'recent' && 'is-active'" @click="go('recent')" aria-label="recent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'smileys' && 'is-active'" @click="go('smileys')" aria-label="smileys"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><circle cx="12" cy="12" r="9"/><path d="M8.5 14c.9 1.2 2 1.8 3.5 1.8s2.6-.6 3.5-1.8"/><path d="M9 9.5h.01M15 9.5h.01" stroke-width="2.4"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'animals' && 'is-active'" @click="go('animals')" aria-label="animals"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M5 19c0-8 5-13 14-14 0 9-5 14-14 14Zm0 0 8-8"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'food' && 'is-active'" @click="go('food')" aria-label="food"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M5 8h11v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Zm11 1h2a2 2 0 0 1 0 4h-2M7 3v2M11 3v2"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'activities' && 'is-active'" @click="go('activities')" aria-label="activities"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'travel' && 'is-active'" @click="go('travel')" aria-label="travel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M3 16v-4l2-5h14l2 5v4H3ZM3 12h18M6 16v2.5M18 16v2.5"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'objects' && 'is-active'" @click="go('objects')" aria-label="objects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.7.6 1 1.4 1 2.1h5c0-.7.3-1.5 1-2.1A6 6 0 0 0 12 3Z"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'symbols' && 'is-active'" @click="go('symbols')" aria-label="symbols"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M5 7h6M8 4v6M14 17h6M5.5 15l5 5M10.5 15l-5 5M14 7h6"/></svg></button>
                <button type="button" class="emoji-tab" :class="tab === 'flags' && 'is-active'" @click="go('flags')" aria-label="flags"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M5 21V4m0 0h12l-2 4 2 4H5"/></svg></button>
            </div>
            <div class="px-3 pt-2.5 pb-1">
                <label class="emoji-search flex items-center gap-2 rounded-full px-3 py-1.5">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input x-ref="search" x-model="query" type="text" placeholder="{{ __('messages.emoji_search') }}" class="flex-1 min-w-0 bg-transparent border-0 p-0 text-sm outline-none focus:ring-0" autocomplete="off">
                </label>
            </div>
            <div x-ref="list" class="emoji-list flex-1 overflow-y-auto px-3 pb-3" @scroll.passive="onScroll()">
                <p x-show="loading" class="text-xs text-center py-6 emoji-muted">…</p>
                <template x-if="query.trim()">
                    <div>
                        <div class="emoji-grid" x-show="results.length"><template x-for="e in results" :key="e"><button type="button" @click="pick(e)" x-text="e"></button></template></div>
                        <p x-show="!results.length && !loading" class="text-sm text-center py-8 emoji-muted">{{ __('messages.emoji_none') }}</p>
                    </div>
                </template>
                <template x-if="!query.trim()">
                    <div>
                        <section data-cat="recent">
                            <h4 class="emoji-heading">{{ __('messages.emoji_recent') }}</h4>
                            <div class="emoji-grid"><template x-for="e in recent" :key="e"><button type="button" @click="pick(e)" x-text="e"></button></template></div>
                            <p x-show="!recent.length" class="text-xs py-2 emoji-muted">{{ __('messages.emoji_recent_empty') }}</p>
                        </section>
                        <template x-for="cat in cats" :key="cat">
                            <section :data-cat="cat">
                                <h4 class="emoji-heading" x-text="labels[cat]"></h4>
                                <div class="emoji-grid"><template x-for="item in (data?.[cat] || [])" :key="item[0]"><button type="button" @click="pick(item[0])" x-text="item[0]"></button></template></div>
                            </section>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-end gap-2">
            <div class="chat-input-pill flex-1 flex items-end gap-1 rounded-3xl px-2 py-1.5">
                <button type="button" @click="emojiOpen = !emojiOpen" class="chat-input-icon shrink-0 w-9 h-9 grid place-items-center rounded-full" aria-label="Emoji">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M8 14.5c1 1.4 2.4 2.1 4 2.1s3-.7 4-2.1"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>
                </button>
                <textarea x-ref="input" title="{{ __('messages.bold_hint') }}" x-model="body" rows="1" required
                          aria-label="{{ __('messages.placeholder') }}"
                          @keydown="onKey($event, 'send')"
                          @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 168) + 'px'"
                          placeholder="{{ __('messages.placeholder') }}"
                          class="chat-textarea flex-1 resize-none bg-transparent border-0 px-1 py-2 text-[15px] leading-snug text-ink outline-none focus:ring-0 max-h-[168px] overflow-y-auto"></textarea>
            </div>
            <button type="submit" :disabled="sending || body.trim() === ''" class="chat-send shrink-0 inline-flex items-center justify-center rounded-full w-12 h-12 disabled:opacity-60 disabled:cursor-not-allowed" aria-label="{{ __('messages.send') }}">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4 21.9 12.5a.6.6 0 0 0 0-1.1L3.4 3.6a.6.6 0 0 0-.8.7L4.8 10.8l9.2 1.2-9.2 1.2-2.2 6.5a.6.6 0 0 0 .8.7Z"/></svg>
            </button>
        </div>
    </form>
</div>

{{-- Vite's module scripts are deferred, so this registers before Alpine.start(). --}}
@once
    <style>
        .chat-emoji-panel { width: min(26rem, calc(100vw - 2rem)); height: 22rem; }
        .emoji-list { overflow-x: hidden; }
        .emoji-tabs { border-bottom: 1px solid rgba(0,0,0,.06); }
        .dark .emoji-tabs { border-color: rgba(255,255,255,.08); }
        .emoji-tab { flex: 1; display: grid; place-items: center; height: 38px; color: #54656f; border-bottom: 2px solid transparent; }
        .emoji-tab:hover { color: #111b21; }
        .emoji-tab.is-active { color: #1fa855; border-bottom-color: #1fa855; }
        .dark .emoji-tab { color: #8696a0; }
        .dark .emoji-tab:hover { color: #e9edef; }
        .dark .emoji-tab.is-active { color: #00a884; border-bottom-color: #00a884; }
        .emoji-search { border: 2px solid #1fa855; color: #54656f; background: transparent; }
        .dark .emoji-search { border-color: #00a884; color: #8696a0; }
        .emoji-search input { color: #111b21; }
        .dark .emoji-search input { color: #e9edef; }
        .emoji-heading { font-size: 13px; font-weight: 600; color: #54656f; margin: 10px 0 4px; }
        .dark .emoji-heading { color: #8696a0; }
        .emoji-muted { color: #667781; }
        .emoji-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(2.1rem, 1fr)); gap: 2px; }
        .emoji-grid button { font-size: 1.45rem; line-height: 1; height: 2.1rem; border-radius: 8px; }
        .emoji-grid button:hover { background: rgba(0,0,0,.07); }
        .dark .emoji-grid button:hover { background: rgba(255,255,255,.1); }
        .chat-edit-btn { float: right; margin: 1px 0 0 8px; padding: 2px; border-radius: 6px; color: #667781; opacity: 0; transition: opacity .15s; }
        .chat-bubble:hover .chat-edit-btn, .chat-edit-btn:focus-visible { opacity: 1; }
        @media (hover: none) { .chat-edit-btn { opacity: .7; } }
        .chat-edit-btn:hover { color: #111b21; background: rgba(0,0,0,.07); }
        .dark .chat-edit-btn { color: #8696a0; }
        .dark .chat-edit-btn:hover { color: #e9edef; background: rgba(255,255,255,.1); }
        .chat-edit-btn.is-danger:hover { color: #e11d48; background: rgba(225,29,72,.1); }
        .chat-editor-actions button.is-danger-solid { background: #e11d48; border-color: #e11d48; color: #fff; }
        .chat-edited { font-style: normal; margin-right: 4px; opacity: .85; }
        .chat-editor { min-width: min(18rem, 60vw); }
        .chat-editor textarea { width: 100%; resize: vertical; border-radius: 6px; border: 1px solid rgba(0,0,0,.18); background: rgba(255,255,255,.7); color: inherit; padding: 6px 8px; font-size: 14.5px; outline: none; }
        .dark .chat-editor textarea { background: rgba(0,0,0,.25); border-color: rgba(255,255,255,.2); }
        .chat-editor-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 5px; }
        .chat-editor-actions button { font-size: 12.5px; font-weight: 600; padding: 3px 12px; border-radius: 999px; border: 1px solid rgba(0,0,0,.2); }
        .chat-editor-actions button.is-primary { background: #1fa855; border-color: #1fa855; color: #fff; }
        .chat-editor-actions button:disabled { opacity: .5; cursor: not-allowed; }
        .chat-composer { background: #f0f2f5; }
        .dark .chat-composer { background: #202c33; }
        .chat-input-pill { background: #fff; box-shadow: 0 1px 0 rgba(0,0,0,.06); }
        .dark .chat-input-pill { background: #2a3942; }
        .chat-input-icon { color: #54656f; }
        .dark .chat-input-icon { color: #8696a0; }
        .chat-input-icon:hover { color: #111b21; }
        .dark .chat-input-icon:hover { color: #e9edef; }
        .chat-textarea::placeholder { color: #667781; }
        .chat-textarea::-webkit-scrollbar { width: 6px; }
        .chat-textarea::-webkit-scrollbar-thumb { background: rgba(0,0,0,.25); border-radius: 3px; }
        .chat-send { background: #1fa855; color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: background .15s, transform .15s; }
        .chat-send:hover:not(:disabled) { background: #1a9449; transform: scale(1.05); }
        .dark .chat-send { background: #00a884; }
        .chat-emoji-panel { background: #fff; border: 1px solid rgba(0,0,0,.08); }
        .dark .chat-emoji-panel { background: #233138; border-color: rgba(255,255,255,.08); }
        .chat-wall { background-color: #efeae2; background-image: radial-gradient(rgba(0,0,0,.045) 1px, transparent 1px); background-size: 18px 18px; }
        .dark .chat-wall { background-color: #0b141a; background-image: radial-gradient(rgba(255,255,255,.035) 1px, transparent 1px); }
        .chat-day { display: inline-block; font-size: 12px; padding: 3px 12px; border-radius: 8px; background: #fff; color: #54656f; box-shadow: 0 1px 0 rgba(0,0,0,.08); }
        .dark .chat-day { background: #182229; color: #8696a0; }
        .chat-bubble { position: relative; max-width: min(78%, 34rem); padding: 5px 8px 6px 9px; border-radius: 8px; font-size: 14.5px; line-height: 1.45; box-shadow: 0 1px 0 rgba(0,0,0,.12); overflow-wrap: anywhere; }
        .chat-theirs { background: #fff; color: #111b21; border-top-left-radius: 0; margin-left: 8px; }
        .chat-mine { background: #d9fdd3; color: #111b21; border-top-right-radius: 0; margin-right: 8px; }
        .dark .chat-theirs { background: #202c33; color: #e9edef; }
        .dark .chat-mine { background: #005c4b; color: #e9edef; }
        .chat-theirs::before, .chat-mine::before { content: ""; position: absolute; top: 0; width: 8px; height: 13px; }
        .chat-theirs::before { left: -8px; background: #fff; clip-path: polygon(100% 0, 0 0, 100% 100%); }
        .chat-mine::before { right: -8px; background: #d9fdd3; clip-path: polygon(0 0, 100% 0, 0 100%); }
        .dark .chat-theirs::before { background: #202c33; }
        .dark .chat-mine::before { background: #005c4b; }
        .chat-author { display: block; font-size: 12.5px; font-weight: 700; color: #06956b; margin-bottom: 1px; }
        .chat-text { white-space: pre-wrap; }
        .chat-text strong { font-weight: 800; }
        .chat-meta { float: right; margin: 7px 0 -4px 12px; display: inline-flex; align-items: center; gap: 3px; font-size: 11px; line-height: 1; color: #667781; }
        .dark .chat-meta { color: #8696a0; }
        .chat-tick { width: 16px; height: 11px; color: #8696a0; }
        .chat-tick.is-read { color: #53bdeb; }
        .chat-bubble::after { content: ""; display: block; clear: both; }
    </style>
    <script>
        window.emojiPicker = function () {
            return {
                emojiOpen: false, loading: false, data: null, query: '', tab: 'recent', recent: [],
                cats: ['smileys', 'animals', 'food', 'activities', 'travel', 'objects', 'symbols', 'flags'],
                labels: @js(['smileys' => __('messages.emoji_smileys'), 'animals' => __('messages.emoji_animals'), 'food' => __('messages.emoji_food'), 'activities' => __('messages.emoji_activities'), 'travel' => __('messages.emoji_travel'), 'objects' => __('messages.emoji_objects'), 'symbols' => __('messages.emoji_symbols'), 'flags' => __('messages.emoji_flags')]),

                init() {
                    try { this.recent = JSON.parse(localStorage.getItem('emoji-recent') || '[]').slice(0, 24); } catch (e) {}
                    this.$watch('emojiOpen', async (open) => {
                        if (!open) return;
                        if (!this.data) {
                            this.loading = true;
                            try { const d = await window.loadEmojiData(); try { await document.fonts.load('28px "Twemoji Country Flags"', '\u{1F1E7}\u{1F1E9}'); } catch (e2) {} this.data = this.onlyRenderable(d); } catch (e) { this.data = {}; }
                            this.loading = false;
                        }
                        this.$nextTick(() => this.$refs.search?.focus());
                    });
                },

                // Keep only emoji this device can actually draw: a font that predates an emoji paints
                // nothing (blank gap), a box, or a ZWJ sequence split into several glyphs.
                onlyRenderable(data) {
                    const cv = document.createElement('canvas');
                    cv.width = 48; cv.height = 40;
                    const ctx = cv.getContext('2d', { willReadFrequently: true });
                    if (!ctx) return data;
                    ctx.font = '28px "Twemoji Country Flags","Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
                    ctx.textBaseline = 'top';
                    const sig = (ch) => {
                        ctx.clearRect(0, 0, 48, 40);
                        ctx.fillText(ch, 2, 2);
                        const px = ctx.getImageData(0, 0, 48, 40).data;
                        let ink = 0, h = 0;
                        for (let i = 3; i < px.length; i += 4) { if (px[i]) ink++; h = (h * 31 + px[i]) | 0; }
                        return { ink, h, w: ctx.measureText(ch).width };
                    };
                    const tofu = sig('\u{10FFFE}').h;
                    const base = ctx.measureText('\u{1F600}').width;
                    const out = {};
                    for (const cat of Object.keys(data)) {
                        out[cat] = data[cat].filter(([e]) => {
                            const r = sig(e);
                            return r.ink > 20 && r.h !== tofu && r.w <= base * 1.35;
                        });
                    }
                    return out;
                },

                get results() {
                    const q = this.query.trim().toLowerCase();
                    if (!q || !this.data) return [];
                    const out = [];
                    for (const cat of this.cats) for (const [e, text] of (this.data[cat] || [])) if (text.includes(q)) out.push(e);
                    return out.slice(0, 200);
                },

                go(cat) {
                    this.query = '';
                    this.tab = cat;
                    this.$nextTick(() => {
                        const el = this.$refs.list.querySelector('[data-cat="' + cat + '"]');
                        if (el) this.$refs.list.scrollTo({ top: el.offsetTop - this.$refs.list.offsetTop, behavior: 'smooth' });
                    });
                },

                onScroll() {
                    const list = this.$refs.list, top = list.scrollTop + list.offsetTop + 8;
                    let current = 'recent';
                    list.querySelectorAll('section[data-cat]').forEach((sec) => { if (sec.offsetTop <= top) current = sec.dataset.cat; });
                    this.tab = current;
                },

                pick(e) {
                    const t = this.$refs.input, i = t.selectionStart ?? this.body.length, j = t.selectionEnd ?? i;
                    this.body = this.body.slice(0, i) + e + this.body.slice(j);
                    this.recent = [e, ...this.recent.filter((x) => x !== e)].slice(0, 24);
                    try { localStorage.setItem('emoji-recent', JSON.stringify(this.recent)); } catch (err) {}
                    this.$nextTick(() => { t.focus(); t.selectionStart = t.selectionEnd = i + e.length; t.dispatchEvent(new Event('input')); });
                },
            };
        };
        window.messageThread = function (endpoints) {
            return {
                    incoming: [],
                    canEdit: !!endpoints.edit, canDelete: !!endpoints.del, deletingId: null, removed: {},
                    editingId: null, editText: '', editError: null, saving: false, edits: {},
                    lastId: endpoints.lastId,
                    lastDay: endpoints.lastDay,
                    body: '',
                    sending: false,
                    error: null,
                    csrf: document.querySelector('meta[name="csrf-token"]')?.content,

                    init() {
                        this.$nextTick(() => this.toBottom());
                        // Chat should feel live, so 10s while it's active; but nothing is fetched
                        // in a hidden tab, and it backs off (up to 60s) while the thread is quiet.
                        this._poller = window.pollWhenVisible(() => this.poll(), { base: 10000, max: 60000 });
                    },

                    destroy() { this._poller?.stop(); },

                    // One key handler for the composer and the inline editors.
                    //   Enter            send / save       Shift+Enter  new line (default)
                    //   Ctrl/Cmd+B       bold              Esc          cancel an edit
                    // Enter is ignored while an input method (e.g. Avro phonetic Bengali) is still
                    // composing a word, where it only confirms the candidate.
                    onKey(e, mode, id) {
                        if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'b' || e.key === 'B')) {
                            e.preventDefault();
                            this.bold(e.target, mode === 'edit' ? 'editText' : 'body');
                        } else if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey && !e.isComposing && e.keyCode !== 229) {
                            e.preventDefault();
                            mode === 'edit' ? this.saveEdit(id) : this.send();
                        } else if (e.key === 'Escape' && mode === 'edit') {
                            this.cancelEdit();
                        }
                    },

                    fmt(s) {
                        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;')
                            .replace(/\*(?=\S)([^*\n]*?\S)\*/g, '<strong>$1</strong>');
                    },

                    // Ctrl/Cmd+B: wrap the selection in *…* (WhatsApp-style bold); on an already
                    // bold selection, unwrap it. With nothing selected, drop a pair and sit between.
                    bold(t, field = 'body') {
                        const a = t.selectionStart, b = t.selectionEnd, v = t.value, sel = v.slice(a, b);
                        let next, from, to;
                        if (sel.length >= 2 && sel.startsWith('*') && sel.endsWith('*')) {
                            next = v.slice(0, a) + sel.slice(1, -1) + v.slice(b); from = a; to = b - 2;
                        } else {
                            next = v.slice(0, a) + '*' + sel + '*' + v.slice(b); from = a + 1; to = b + 1;
                        }
                        this[field] = next;
                        this.$nextTick(() => { t.focus(); t.setSelectionRange(from, to); t.dispatchEvent(new Event('input')); });
                    },

                    startEdit(id, text) {
                        this.editingId = id; this.editText = this.edits[id] ?? text; this.editError = null;
                    },

                    askDelete(id) { this.editingId = null; this.deletingId = id; },

                    async doDelete(id) {
                        if (this.saving) return;
                        this.saving = true;
                        try {
                            const res = await fetch(endpoints.del.replace('__ID__', id), {
                                method: 'DELETE',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                            });
                            if (!res.ok) throw new Error('delete failed');
                            this.incoming = this.incoming.filter((m) => m.id !== id);
                            this.removed[id] = true;
                            this.deletingId = null;
                        } catch (e) {
                            this.error = @js(__('messages.delete_failed'));
                            this.deletingId = null;
                        } finally {
                            this.saving = false;
                        }
                    },

                    cancelEdit() { this.editingId = null; this.editError = null; },

                    async saveEdit(id) {
                        const body = this.editText.trim();
                        if (body === '' || this.saving) return;
                        this.saving = true; this.editError = null;
                        try {
                            const res = await fetch(endpoints.edit.replace('__ID__', id), {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                                body: JSON.stringify({ body }),
                            });
                            if (!res.ok) throw new Error('edit failed');
                            const data = await res.json();
                            const live = this.incoming.find((m) => m.id === id);
                            if (live) { live.body = data.message.body; live.edited = true; } else { this.edits[id] = data.message.body; }
                            this.editingId = null;
                        } catch (e) {
                            this.editError = @js(__('messages.edit_failed'));
                        } finally {
                            this.saving = false;
                        }
                    },

                    toBottom() {
                        const el = this.$refs.scroll;
                        if (el) el.scrollTop = el.scrollHeight;
                    },

                    append(message) {
                        message.showDay = message.day !== this.lastDay;
                        this.lastDay = message.day;
                        this.incoming.push(message);
                        this.lastId = Math.max(this.lastId, message.id);
                        this.$nextTick(() => this.toBottom());
                    },

                    async poll() {
                        try {
                            const res = await fetch(endpoints.poll + '?after=' + this.lastId, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (!res.ok) return false;
                            const data = await res.json();
                            const fresh = data.messages || [];
                            fresh.forEach((m) => this.append(m));
                            return fresh.length > 0;   // true = activity, keep polling fast
                        } catch (e) {
                            // A dropped poll is harmless — the next tick catches up.
                            return false;
                        }
                    },

                    async send() {
                        const body = this.body.trim();
                        if (body === '' || this.sending) return;

                        this.sending = true;
                        this.error = null;

                        try {
                            const res = await fetch(endpoints.send, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                },
                                body: JSON.stringify({ body }),
                            });

                            if (!res.ok) throw new Error('send failed');

                            const data = await res.json();
                            this.append(data.message);
                            this._poller?.reset();   // a reply may follow soon: back to the fast rate
                            this.body = '';
                            const box = this.$root.querySelector('.chat-textarea'); if (box) box.style.height = 'auto';
                        } catch (e) {
                            this.error = @js(__('messages.send_failed'));
                        } finally {
                            this.sending = false;
                        }
                    },
            };
        };
    </script>
@endonce
