@php
    $en = app()->getLocale() === 'en';
    $when = fn ($d) => $d ? ($en ? $d->format('j M Y, g:i A') : bn($d->format('d/m/Y H:i'))) : null;

    // One tab per topic. Each card inside posts only its own fields, so saving one never touches another.
    $tabs = [
        'general' => ['icon' => 'settings', 'label' => __('settings.tab_general'), 'fields' => ['site_title', 'site_tagline']],
        'email' => ['icon' => 'message', 'label' => __('settings.tab_email'), 'fields' => ['ustaz_email', 'contact_email']],
        'about' => ['icon' => 'profile', 'label' => __('settings.about_heading'), 'fields' => ['about_bio', 'about_photo']],
        'social' => ['icon' => 'share', 'label' => __('settings.tab_social'), 'fields' => ['telegram_url', 'whatsapp_url', 'facebook_page_url', 'facebook_group_url', 'dev_name_bn', 'dev_name_en', 'dev_whatsapp_url', 'dev_linkedin_url']],
        'zakat' => ['icon' => 'points', 'label' => __('settings.tab_zakat'), 'fields' => ['gold_price_per_gram', 'silver_price_per_gram', 'nisab_basis', 'currency_label']],
        'calendar' => ['icon' => 'calendar', 'label' => __('settings.tab_calendar'), 'fields' => ['calendar_latitude', 'calendar_longitude', 'calendar_location_label', 'prayer_fajr_angle', 'prayer_isha_angle', 'prayer_asr_factor', 'calendar_timezone', 'hijri_offset_days']],
    ];
    // After a failed save, open the tab that holds the first invalid field.
    $errorTab = collect($tabs)->search(fn ($t) => collect($t['fields'])->contains(fn ($f) => $errors->has($f)));
@endphp

{{-- A settings section: what it is on the left, the form on the right (stacked on phones). --}}
@php
    $section = 'grid gap-4 lg:grid-cols-[15rem_1fr] lg:gap-8 py-6 first:pt-0 last:pb-0';
    $footer = 'flex justify-end border-t border-line pt-4 mt-5';
@endphp

<x-layout.admin :title="__('settings.heading')" :heading="__('settings.heading')">
    <div x-data="{
            tab: @js($errorTab ?: null) || (() => { try { return sessionStorage.getItem('settings-tab') } catch (e) { return null } })() || 'general',
            go(t) { this.tab = t; try { sessionStorage.setItem('settings-tab', t) } catch (e) {} },
         }"
         x-init="if (! @js(array_keys($tabs)).includes(tab)) tab = 'general'"
         class="max-w-5xl">

        {{-- Tabs --}}
        <nav class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto mb-6" aria-label="{{ __('settings.heading') }}">
            <div class="inline-flex gap-1 rounded-2xl border border-line bg-card p-1 shadow-[--shadow-soft]" role="tablist">
                @foreach ($tabs as $key => $t)
                    <button type="button" role="tab" @click="go('{{ $key }}')" :aria-selected="tab === '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'bg-brand text-brand-ink shadow-sm' : 'text-muted hover:text-ink hover:bg-surface-raised'"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition-colors">
                        <x-ui.icon :name="$t['icon']" class="w-4 h-4" />
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </div>
        </nav>

        <x-ui.card :padded="false" class="p-5 sm:p-7">

            {{-- ── General ─────────────────────────────────────────────────────── --}}
            <div x-show="tab === 'general'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.site_info') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.site_info_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.general') }}">
                        @csrf @method('PUT')
                        <div class="space-y-4">
                            <x-ui.field :label="__('settings.site_title')" name="site_title" :required="true">
                                <x-ui.input name="site_title" value="{{ old('site_title', $general['site_title']) }}" required />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.site_tagline')" name="site_tagline">
                                <x-ui.input name="site_tagline" value="{{ old('site_tagline', $general['site_tagline']) }}" />
                            </x-ui.field>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>
            </div>

            {{-- ── Email ───────────────────────────────────────────────────────── --}}
            <div x-show="tab === 'email'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.recipients') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.recipients_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.general') }}">
                        @csrf @method('PUT')
                        <div class="space-y-4">
                            <x-ui.field :label="__('settings.ustaz_email')" name="ustaz_email" :hint="__('settings.ustaz_email_hint')">
                                <x-ui.input type="email" name="ustaz_email" value="{{ old('ustaz_email', $general['ustaz_email']) }}" placeholder="ustaz@example.com" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.contact_email')" name="contact_email" :hint="__('settings.contact_email_hint')">
                                <x-ui.input type="email" name="contact_email" value="{{ old('contact_email', $general['contact_email']) }}" placeholder="contact@example.com" dir="ltr" />
                            </x-ui.field>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>

                {{-- Mail diagnostic (never reveals secrets) --}}
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.mail') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.mail_note') }}</p>
                    </div>
                    <dl class="rounded-xl border border-line divide-y divide-line text-sm">
                        @foreach ([
                            [__('settings.mail_status'), $mail['configured'], __('settings.mail_configured'), __('settings.mail_incomplete')],
                            [__('settings.mail_from'), $mail['from'], __('settings.set'), __('settings.missing')],
                            [__('settings.ustaz_recipient'), $mail['ustaz'], __('settings.set'), __('settings.missing')],
                            [__('settings.contact_recipient'), $mail['contact'], __('settings.set'), __('settings.missing')],
                        ] as [$label, $ok, $yes, $no])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <dt class="text-muted">{{ $label }}</dt>
                                <dd><x-ui.badge :color="$ok ? 'success' : 'warning'">{{ $ok ? $yes : $no }}</x-ui.badge></dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>

            {{-- ── About the ustaz ─────────────────────────────────────────────── --}}
            <div x-show="tab === 'about'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.about_heading') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.about_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.about') }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <div class="space-y-5">
                            <div class="flex items-center gap-4">
                                @if ($about['about_photo'])
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.uploads_disk'))->url($about['about_photo']) }}" alt="{{ __('settings.current_photo') }}"
                                         class="size-20 rounded-2xl object-cover border border-line shrink-0">
                                @else
                                    <span class="size-20 rounded-2xl border border-dashed border-line grid place-items-center text-muted shrink-0"><x-ui.icon name="profile" class="w-7 h-7" /></span>
                                @endif
                                <x-ui.field :label="__('settings.about_photo')" name="about_photo" class="flex-1 min-w-0">
                                    <input type="file" name="about_photo" accept="image/png,image/jpeg,image/webp"
                                           class="w-full text-sm text-ink file:me-3 file:rounded-lg file:border-0 file:bg-brand-tint file:text-brand-strong file:px-3 file:py-1.5 file:text-sm file:font-semibold">
                                </x-ui.field>
                            </div>
                            <x-ui.field :label="__('settings.about_bio')" name="about_bio" :hint="__('settings.about_bio_hint')">
                                <x-ui.rich-editor name="about_bio" :label="__('settings.about_bio')"
                                                  :value="\App\Support\RichText::toHtml(old('about_bio', $about['about_bio']))"
                                                  :placeholder="__('settings.about_bio_placeholder')" />
                            </x-ui.field>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>
            </div>

            {{-- ── Social links + developer credit ─────────────────────────────── --}}
            <div x-show="tab === 'social'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.social_links') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.social_links_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.general') }}">
                        @csrf @method('PUT')
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-ui.field :label="__('settings.facebook_page_url')" name="facebook_page_url">
                                <x-ui.input name="facebook_page_url" value="{{ old('facebook_page_url', $general['facebook_page_url']) }}" placeholder="https://facebook.com/…" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.facebook_group_url')" name="facebook_group_url">
                                <x-ui.input name="facebook_group_url" value="{{ old('facebook_group_url', $general['facebook_group_url']) }}" placeholder="https://facebook.com/groups/…" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.telegram_url')" name="telegram_url">
                                <x-ui.input name="telegram_url" value="{{ old('telegram_url', $general['telegram_url']) }}" placeholder="https://t.me/…" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.whatsapp_url')" name="whatsapp_url">
                                <x-ui.input name="whatsapp_url" value="{{ old('whatsapp_url', $general['whatsapp_url']) }}" placeholder="https://wa.me/8801XXXXXXXXX" dir="ltr" />
                            </x-ui.field>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>

                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.dev_credit') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.dev_credit_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.general') }}">
                        @csrf @method('PUT')
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-ui.field :label="__('settings.dev_name_bn')" name="dev_name_bn">
                                <x-ui.input name="dev_name_bn" value="{{ old('dev_name_bn', $general['dev_name_bn']) }}" maxlength="100" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.dev_name_en')" name="dev_name_en">
                                <x-ui.input name="dev_name_en" value="{{ old('dev_name_en', $general['dev_name_en']) }}" maxlength="100" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.dev_whatsapp_url')" name="dev_whatsapp_url">
                                <x-ui.input name="dev_whatsapp_url" value="{{ old('dev_whatsapp_url', $general['dev_whatsapp_url']) }}" placeholder="https://wa.me/8801XXXXXXXXX" dir="ltr" />
                            </x-ui.field>
                            <x-ui.field :label="__('settings.dev_linkedin_url')" name="dev_linkedin_url">
                                <x-ui.input name="dev_linkedin_url" value="{{ old('dev_linkedin_url', $general['dev_linkedin_url']) }}" placeholder="https://www.linkedin.com/in/…" dir="ltr" />
                            </x-ui.field>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>
            </div>

            {{-- ── Zakat ───────────────────────────────────────────────────────── --}}
            <div x-show="tab === 'zakat'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.zakat') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">
                            {{ ($goldUpdatedAt || $silverUpdatedAt) ? __('settings.rates_updated', ['when' => $when($goldUpdatedAt ?? $silverUpdatedAt)]) : __('settings.rates_never') }}
                        </p>
                    </div>
                    @php
                        // Reference weights come from the calculator itself so this preview can never disagree with it.
                        $zk = [
                            'goldGrams' => (float) \App\Services\Zakat\ZakatCalculatorService::NISAB_GOLD_GRAMS,
                            'silverGrams' => (float) \App\Services\Zakat\ZakatCalculatorService::NISAB_SILVER_GRAMS,
                            'vori' => (float) \App\Services\Zakat\ZakatCalculatorService::VORI_TO_GRAM,
                            'bn' => app()->getLocale() !== 'en',
                            'gold' => (string) old('gold_price_per_gram', $zakat['gold_price_per_gram']),
                            'silver' => (string) old('silver_price_per_gram', $zakat['silver_price_per_gram']),
                            'cur' => (string) old('currency_label', $zakat['currency_label']),
                        ];
                    @endphp
                    <form method="POST" action="{{ route('admin.settings.zakat') }}"
                          x-data="{ z: @js($zk),
                              fmt(n) { let s = (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); if (this.z.bn) s = s.replace(/[0-9]/g, c => '০১২৩৪৫৬৭৮৯'[c]); return s; },
                              line(label, grams, rate) {
                                  const r = parseFloat(rate);
                                  const vori = Math.round(grams / this.z.vori * 100) / 100;
                                  const base = label + ': ' + this.fmt(grams) + ' ' + @js(__('settings.grams')) + ' (' + this.fmt(vori) + ' ' + @js(__('settings.vori')) + ')';
                                  return (r > 0) ? base + ' × ' + this.z.cur + ' ' + this.fmt(r) + ' = ' + this.z.cur + ' ' + this.fmt(grams * r) : base + ' — ' + @js(__('settings.price_missing'));
                              } }">
                        @csrf @method('PUT')
                        <div class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-ui.field :label="__('settings.gold_price')" name="gold_price_per_gram">
                                    <x-ui.input type="number" step="0.01" min="0" name="gold_price_per_gram" x-model="z.gold" value="{{ old('gold_price_per_gram', $zakat['gold_price_per_gram']) }}" />
                                </x-ui.field>
                                <x-ui.field :label="__('settings.silver_price')" name="silver_price_per_gram">
                                    <x-ui.input type="number" step="0.01" min="0" name="silver_price_per_gram" x-model="z.silver" value="{{ old('silver_price_per_gram', $zakat['silver_price_per_gram']) }}" />
                                </x-ui.field>
                                <x-ui.field :label="__('settings.nisab_basis')" name="nisab_basis">
                                    <x-ui.select name="nisab_basis">
                                        <option value="silver" @selected($zakat['nisab_basis'] === 'silver')>{{ __('settings.basis_silver') }}</option>
                                        <option value="gold" @selected($zakat['nisab_basis'] === 'gold')>{{ __('settings.basis_gold') }}</option>
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field :label="__('settings.currency_label')" name="currency_label" :required="true">
                                    <x-ui.input name="currency_label" x-model="z.cur" value="{{ old('currency_label', $zakat['currency_label']) }}" required />
                                </x-ui.field>
                            </div>
                            {{-- Live preview: what the public calculator will use as the Nisab, so a typo in a price is obvious before saving. --}}
                            <div class="rounded-xl bg-brand-tint/50 border border-line p-3 text-sm space-y-1.5" aria-live="polite">
                                <p class="text-xs font-semibold text-muted">{{ __('settings.nisab_preview') }}</p>
                                <p class="text-ink tabular-nums" x-text="line(@js(__('settings.basis_silver')), z.silverGrams, z.silver)"></p>
                                <p class="text-ink tabular-nums" x-text="line(@js(__('settings.basis_gold')), z.goldGrams, z.gold)"></p>
                            </div>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>
            </div>

            {{-- ── Calendar & prayer times ─────────────────────────────────────── --}}
            <div x-show="tab === 'calendar'" x-cloak role="tabpanel" class="divide-y divide-line">
                <section class="{{ $section }}">
                    <div>
                        <h2 class="font-bold text-ink">{{ __('settings.tab_calendar') }}</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">{{ __('settings.location_hint') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.calendar') }}">
                        @csrf @method('PUT')
                        <div class="space-y-5">
                            <fieldset class="space-y-4">
                                <legend class="text-sm font-semibold text-ink mb-3">{{ __('settings.location') }}</legend>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <x-ui.field :label="__('settings.latitude')" name="calendar_latitude" :required="true">
                                        <x-ui.input type="number" step="0.0001" name="calendar_latitude" value="{{ old('calendar_latitude', $calendar['calendar_latitude']) }}" required />
                                    </x-ui.field>
                                    <x-ui.field :label="__('settings.longitude')" name="calendar_longitude" :required="true">
                                        <x-ui.input type="number" step="0.0001" name="calendar_longitude" value="{{ old('calendar_longitude', $calendar['calendar_longitude']) }}" required />
                                    </x-ui.field>
                                    <x-ui.field :label="__('settings.location_label')" name="calendar_location_label" :hint="__('settings.location_label_hint')">
                                        <x-ui.input name="calendar_location_label" value="{{ old('calendar_location_label', $calendar['calendar_location_label'] ?? '') }}" maxlength="100" />
                                    </x-ui.field>
                                    <x-ui.field :label="__('settings.timezone')" name="calendar_timezone" :required="true">
                                        <x-ui.input name="calendar_timezone" value="{{ old('calendar_timezone', $calendar['calendar_timezone']) }}" required dir="ltr" />
                                    </x-ui.field>
                                </div>
                            </fieldset>

                            <fieldset class="space-y-4 border-t border-line pt-5">
                                <legend class="sr-only">{{ __('settings.prayer_times') }}</legend>
                                <p class="text-sm font-semibold text-ink">{{ __('settings.prayer_times') }}</p>
                                <div class="grid sm:grid-cols-3 gap-4">
                                    <x-ui.field :label="__('settings.fajr_angle')" name="prayer_fajr_angle" :required="true">
                                        <x-ui.input type="number" step="0.1" name="prayer_fajr_angle" value="{{ old('prayer_fajr_angle', $calendar['prayer_fajr_angle']) }}" required />
                                    </x-ui.field>
                                    <x-ui.field :label="__('settings.isha_angle')" name="prayer_isha_angle" :required="true">
                                        <x-ui.input type="number" step="0.1" name="prayer_isha_angle" value="{{ old('prayer_isha_angle', $calendar['prayer_isha_angle']) }}" required />
                                    </x-ui.field>
                                    <x-ui.field :label="__('settings.asr_method')" name="prayer_asr_factor" :required="true">
                                        <x-ui.select name="prayer_asr_factor">
                                            <option value="2" @selected((int) old('prayer_asr_factor', $calendar['prayer_asr_factor']) === 2)>{{ __('settings.asr_hanafi') }}</option>
                                            <option value="1" @selected((int) old('prayer_asr_factor', $calendar['prayer_asr_factor']) === 1)>{{ __('settings.asr_shafii') }}</option>
                                        </x-ui.select>
                                    </x-ui.field>
                                </div>
                                <p class="text-xs text-muted">{{ __('settings.prayer_hint') }}</p>
                            </fieldset>

                            <fieldset class="border-t border-line pt-5">
                                <legend class="sr-only">{{ __('settings.hijri_offset') }}</legend>
                                <x-ui.field :label="__('settings.hijri_offset')" name="hijri_offset_days" :hint="__('settings.hijri_offset_hint')" :required="true" class="max-w-xs">
                                    <x-ui.select name="hijri_offset_days">
                                        @foreach (['-1' => '−১', '0' => '০', '1' => '+১'] as $v => $label)
                                            <option value="{{ $v }}" @selected((string) old('hijri_offset_days', $calendar['hijri_offset_days']) === (string) $v)>{{ $en ? strtr($label, ['১' => '1', '০' => '0']) : $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            </fieldset>
                        </div>
                        <div class="{{ $footer }}"><x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button></div>
                    </form>
                </section>
            </div>
        </x-ui.card>
    </div>
</x-layout.admin>
