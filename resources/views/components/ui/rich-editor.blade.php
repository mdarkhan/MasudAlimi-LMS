@props([
    'name',                 // the form field that receives the HTML
    'value' => '',          // initial HTML (already safe — pass RichText::toHtml(...))
    'label' => null,        // accessible name for the toolbar/editor
    'placeholder' => '',
    'minHeight' => 'min-h-[12rem]',
])

{{--
    A small WYSIWYG editor (bold, italic, underline, two heading sizes, lists, quote, link, clear).
    It only emits HTML into a hidden input; the SERVER is the security boundary — every value is reduced
    to App\Support\HtmlSanitizer's allow-list on save and again on display. The paste cleaner below is a
    courtesy that keeps pasted Word/web formatting tidy, not a security control.
    Headings are h3/h4 so they sit under the page's own h2 section titles.
--}}
@php
    $btn = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-ink hover:bg-surface transition-colors';
    $sep = '<span class="mx-1 h-5 w-px bg-line" aria-hidden="true"></span>';
@endphp

<div x-data="richEditor(@js((string) $value))" {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface-raised overflow-hidden focus-within:border-brand transition-colors']) }}>
    <div class="flex flex-wrap items-center gap-0.5 border-b border-line p-1.5" role="toolbar" aria-label="{{ $label }}">
        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('bold')" title="{{ __('ui.rt_bold') }}" aria-label="{{ __('ui.rt_bold') }}"><span class="font-black">B</span></button>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('italic')" title="{{ __('ui.rt_italic') }}" aria-label="{{ __('ui.rt_italic') }}"><span class="italic font-serif text-[15px]">I</span></button>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('underline')" title="{{ __('ui.rt_underline') }}" aria-label="{{ __('ui.rt_underline') }}"><span class="underline">U</span></button>
        {!! $sep !!}
        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('h3')" title="{{ __('ui.rt_heading_1') }}" aria-label="{{ __('ui.rt_heading_1') }}"><span class="font-bold text-xs">H1</span></button>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('h4')" title="{{ __('ui.rt_heading_2') }}" aria-label="{{ __('ui.rt_heading_2') }}"><span class="font-bold text-xs">H2</span></button>
        {!! $sep !!}
        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertUnorderedList')" title="{{ __('ui.rt_bullet') }}" aria-label="{{ __('ui.rt_bullet') }}">
            <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4" aria-hidden="true"><circle cx="3" cy="5" r="1.3"/><circle cx="3" cy="10" r="1.3"/><circle cx="3" cy="15" r="1.3"/><rect x="7" y="4" width="11" height="2" rx="1"/><rect x="7" y="9" width="11" height="2" rx="1"/><rect x="7" y="14" width="11" height="2" rx="1"/></svg>
        </button>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertOrderedList')" title="{{ __('ui.rt_number') }}" aria-label="{{ __('ui.rt_number') }}"><span class="font-bold text-xs tabular-nums">1.</span></button>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('blockquote')" title="{{ __('ui.rt_quote') }}" aria-label="{{ __('ui.rt_quote') }}"><span class="text-lg leading-none">&ldquo;</span></button>
        {!! $sep !!}
        <div class="relative">
            <button type="button" class="{{ $btn }}" @mousedown.prevent="openLink()" @click.stop title="{{ __('ui.rt_link') }}" aria-label="{{ __('ui.rt_link') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l1.42-1.42a5 5 0 0 0-7.07-7.07L10.29 5.6"/><path d="M14 11a5 5 0 0 0-7.07 0L5.5 12.42a5 5 0 0 0 7.07 7.07l1.13-1.12"/></svg>
            </button>
            <div x-show="linkOpen" x-cloak @mousedown.stop @click.outside="linkOpen = false"
                 class="absolute z-30 mt-1 w-72 max-w-[80vw] rounded-xl border border-line bg-card p-3 shadow-lg space-y-2">
                <input type="text" inputmode="url" x-model="linkUrl" x-ref="linkInput" @keydown.enter.prevent="applyLink()"
                       dir="ltr" placeholder="https://…" aria-label="{{ __('ui.rt_link') }}"
                       class="w-full rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                <div class="flex items-center gap-2">
                    <button type="button" @mousedown.prevent="applyLink()" class="flex-1 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-brand-strong">{{ __('ui.rt_apply') }}</button>
                    <button type="button" @mousedown.prevent="removeLink()" class="rounded-lg border border-line px-3 py-1.5 text-sm text-muted hover:text-ink">{{ __('ui.rt_unlink') }}</button>
                </div>
            </div>
        </div>
        <button type="button" class="{{ $btn }}" @mousedown.prevent="clearFormat()" title="{{ __('ui.rt_clear') }}" aria-label="{{ __('ui.rt_clear') }}"><span class="text-xs">&times;</span></button>
    </div>

    <div x-ref="editor" x-init="$el.innerHTML = html"
         contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ $label }}"
         data-placeholder="{{ $placeholder }}"
         @input="sync()" @blur="sync()" @paste="onPaste($event)"
         class="rt-editor rich-text {{ $minHeight }} max-h-[32rem] overflow-y-auto px-4 py-3 text-ink"></div>
    <input type="hidden" name="{{ $name }}" x-ref="field" value="{{ $value }}">
</div>

@once
    <style>
        .rt-editor:empty::before { content: attr(data-placeholder); color: var(--muted); pointer-events: none; }
        .rt-editor:focus { outline: none; }
    </style>
    <script>
        window.richEditor = function (initial) {
            const ALLOWED = { p: [], br: [], strong: [], b: [], em: [], i: [], u: [], s: [], h2: [], h3: [], h4: [],
                ul: [], ol: [], li: [], blockquote: [], span: [], a: ['href'] };
            function clean(node) {
                Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                    if (child.nodeType === 3) return;
                    if (child.nodeType !== 1) { child.remove(); return; }
                    const tag = child.tagName.toLowerCase();
                    if (! (tag in ALLOWED)) {
                        if (tag === 'script' || tag === 'style') { child.remove(); return; }
                        clean(child);
                        while (child.firstChild) node.insertBefore(child.firstChild, child);
                        child.remove();
                        return;
                    }
                    Array.prototype.slice.call(child.attributes).forEach(function (attr) {
                        if (ALLOWED[tag].indexOf(attr.name.toLowerCase()) === -1) child.removeAttribute(attr.name);
                    });
                    clean(child);
                });
            }
            return {
                html: initial || '',
                linkOpen: false,
                linkUrl: 'https://',
                range: null,
                exec(command) {
                    this.$refs.editor.focus();
                    document.execCommand('styleWithCSS', false, false);   // <b>/<i>/<u>, not inline styles
                    document.execCommand(command, false, null);
                    this.sync();
                },
                toggleBlock(tag) {
                    this.$refs.editor.focus();
                    const current = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
                    document.execCommand('formatBlock', false, current === tag ? 'p' : tag);
                    this.sync();
                },
                clearFormat() {
                    this.$refs.editor.focus();
                    document.execCommand('removeFormat');
                    document.execCommand('unlink');
                    document.execCommand('formatBlock', false, 'p');
                    this.sync();
                },
                openLink() {
                    const sel = window.getSelection();
                    this.range = (sel && sel.rangeCount && this.$refs.editor.contains(sel.anchorNode)) ? sel.getRangeAt(0).cloneRange() : null;
                    this.linkOpen = ! this.linkOpen;
                    if (this.linkOpen) this.$nextTick(() => this.$refs.linkInput.focus());
                },
                restore() {
                    this.$refs.editor.focus();
                    if (! this.range) return;
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(this.range);
                },
                applyLink() {
                    const url = (this.linkUrl || '').trim();
                    this.restore();
                    if (url === '' || url === 'https://' || ! /^(https?:|mailto:|\/)/i.test(url)) {
                        document.execCommand('unlink');
                    } else if (window.getSelection().isCollapsed) {
                        const esc = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        document.execCommand('insertHTML', false, '<a href="' + esc + '">' + esc + '</a>');
                    } else {
                        document.execCommand('createLink', false, url);
                    }
                    this.linkOpen = false;
                    this.sync();
                },
                removeLink() {
                    this.restore();
                    document.execCommand('unlink');
                    this.linkOpen = false;
                    this.sync();
                },
                onPaste(event) {
                    const data = event.clipboardData || window.clipboardData;
                    if (! data) return;
                    event.preventDefault();
                    const pasted = data.getData('text/html');
                    if (pasted && pasted.trim() !== '') {
                        const doc = new DOMParser().parseFromString('<div id="__rt">' + pasted + '</div>', 'text/html');
                        const root = doc.getElementById('__rt');
                        if (root) { clean(root); document.execCommand('insertHTML', false, root.innerHTML); }
                    } else {
                        document.execCommand('insertText', false, data.getData('text/plain'));
                    }
                    this.sync();
                },
                sync() {
                    this.html = this.$refs.editor.innerHTML;
                    this.$refs.field.value = this.html;   // synchronous, so a quick Save never submits a stale value
                },
            };
        };
    </script>
@endonce
