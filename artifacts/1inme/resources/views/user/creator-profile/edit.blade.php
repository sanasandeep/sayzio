@extends('user.layouts.settings')
@section('title', 'Creator Profile')
@section('settings-content')
<div>
    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Creator Profile</h1>
            <p class="text-sm mt-1" style="color: var(--text-dimmed);">Your public page at <code>{{ '/@' . ($user->handle ?? 'handle') }}</code></p>
        </div>
        @if($profileUrl)
            <a href="{{ $profileUrl }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-2 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-soft); color: var(--text-primary);">
                <i class="fas fa-up-right-from-square mr-1"></i> View public profile
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="xl:flex xl:items-start xl:gap-6"
         @input.debounce.300ms="pvLive()" @change.debounce.150ms="pvLive()" @click.debounce.300ms="pvLive()"
         x-data="{
             pvMode: ['mini','small','large','full'].includes(localStorage.getItem('cp_pv_mode')) ? localStorage.getItem('cp_pv_mode') : 'small',
             pvSizes: { mini: 220, small: 320, large: 430 },
             pvBase: 390,
             pvBaseH: 760,
             get pvPaneW() { return this.pvSizes[this.pvMode] || this.pvSizes.small; },
             get pvScale() { return (this.pvPaneW - 26) / this.pvBase; },
             setMode(m) { this.pvMode = m; localStorage.setItem('cp_pv_mode', m); },
             pvReload() { const f = this.$refs.pvFrame; if (f) f.src = f.src; const g = this.$refs.pvFrameFull; if (g && this.pvMode === 'full') g.src = g.src; },
             pvField(n) { const el = document.querySelector('[name=' + n + ']'); return el ? el.value : null; },
             pvLive() {
                 const msg = { type: 'cpLive', tagline: this.pvField('tagline'), location: this.pvField('location'), bio: this.pvField('bio'), color: this.pvField('profile_theme_color') };
                 [this.$refs.pvFrame, this.$refs.pvFrameFull].forEach(f => { try { if (f && f.contentWindow) f.contentWindow.postMessage(msg, window.location.origin); } catch (e) {} });
             }
         }">
    <div class="flex-1 min-w-0">
    {{-- ── Completeness meter ───────────────────────────── --}}
    <div class="rounded-2xl p-5 mb-6" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-dimmed);">Profile completeness</p>
            <p class="text-sm font-bold" style="color: var(--text-primary);">{{ $completeness }}%</p>
        </div>
        <div class="h-2 w-full rounded-full overflow-hidden" style="background: rgba(61,107,255,0.1);">
            <div class="h-full bg-gradient-to-r from-blue-500 to-fuchsia-500 transition-all" style="width: {{ $completeness }}%;"></div>
        </div>
        <p class="text-[11px] mt-2" style="color: var(--text-dimmed);">Add a cover, tagline, niche tags, socials, and your first post to reach 100%.</p>
    </div>

    {{-- ── Handle claim ─────────────────────────────────── --}}
    @if(empty($user->handle))
        <div class="rounded-2xl p-5 mb-6 border-l-4 border-blue-500" style="background: rgba(61,107,255,0.04);">
            <p class="text-sm font-bold mb-2" style="color: var(--text-primary);">Claim your handle</p>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Your profile lives at <code>/@yourname</code>. Pick a 3–30 character handle (letters, numbers, underscore).</p>
            <form action="{{ route('user.creator-profile.handle.claim') }}" method="POST" class="flex gap-2 items-center">
                @csrf
                <span class="text-sm font-bold" style="color: var(--text-primary);">@</span>
                <input type="text" name="handle" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+"
                       placeholder="yourname"
                       class="flex-1 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                <button class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Claim</button>
            </form>
        </div>
    @endif

    {{-- ── Main editor ──────────────────────────────────── --}}
    <form action="{{ route('user.creator-profile.update') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-2 items-start" style="display: grid; column-gap: 2rem; row-gap: 3.5rem;">
        @csrf

        {{-- Hero --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Hero</legend>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Profile accent color</label>
                    <p class="text-[11px] mb-2" style="color: var(--text-dimmed);">Used as the hero gradient and accent on your public profile page. Leave blank to use the platform default (blue → fuchsia).</p>
                    @php $__themeColor = old('profile_theme_color', $user->profile_theme_color ?? ''); @endphp
                    <div class="flex items-center gap-3 flex-wrap"
                         x-data="{
                             color: @js($__themeColor),
                             presets: ['#3d6bff','#e11d48','#7c3aed','#0ea5e9','#10b981','#f59e0b','#ec4899','#64748b'],
                             pick(c) { this.color = c; },
                             clear() { this.color = ''; }
                         }">
                        <input type="hidden" name="profile_theme_color" :value="color">
                        {{-- Swatches --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <template x-for="preset in presets" :key="preset">
                                <button type="button"
                                        @click="pick(preset)"
                                        :title="preset"
                                        class="w-7 h-7 rounded-full border-2 transition-all hover:scale-110"
                                        :style="{ background: preset, borderColor: color === preset ? 'var(--text-primary)' : 'transparent' }">
                                </button>
                            </template>
                        </div>
                        {{-- Free-pick hex --}}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="color"
                                   :value="color || '#3d6bff'"
                                   @input="color = $event.target.value"
                                   class="w-8 h-8 rounded-lg cursor-pointer border-0 p-0 bg-transparent"
                                   title="Custom color">
                            <span class="text-xs font-mono" style="color: var(--text-primary);" x-text="color || 'none'"></span>
                        </label>
                        {{-- Clear --}}
                        <button type="button" @click="clear()" x-show="color" class="text-[11px] px-2 py-1 rounded" style="background: var(--bg-soft); color: var(--text-muted);">
                            Reset to default
                        </button>
                        {{-- Live preview swatch --}}
                        <div x-show="color"
                             :style="{ background: 'linear-gradient(135deg, ' + color + ', ' + color + '99)', borderRadius: '8px', width: '64px', height: '28px', border: '1px solid rgba(255,255,255,0.1)' }">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Cover image</label>
                    @if($user->cover_image)
                        <div class="mb-2 relative">
                            <img src="{{ \App\Support\PublicStorageUrl::resolve($user->cover_image) }}" class="w-full h-32 object-cover rounded-lg">
                            <label class="absolute top-2 right-2 text-[11px] px-2 py-1 rounded bg-white/90 text-slate-700 cursor-pointer">
                                <input type="checkbox" name="cover_image_remove" value="1" class="mr-1"> Remove
                            </label>
                        </div>
                    @endif
                    <input type="file" name="cover_image" accept="image/*" class="text-xs">
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Or paste a URL:</p>
                    <input type="url" name="cover_image_url" placeholder="https://…" maxlength="1024"
                           class="w-full px-3 py-2 rounded-lg text-sm mt-1" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Tagline (one-line headline)</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $user->tagline) }}" maxlength="200" placeholder="e.g. Designer & cat collector"
                           class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Location</label>
                    <input type="text" name="location" value="{{ old('location', $user->location) }}" maxlength="120" placeholder="City, Country"
                           class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                </div>
            </div>
        </fieldset>

        {{-- About --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">About</legend>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Bio</label>
                    <textarea name="bio" rows="4" maxlength="2000" placeholder="Tell people who you are."
                              class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">{{ old('bio', $user->bio) }}</textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Niche tags (up to 8)</label>
                    <div class="flex flex-wrap gap-2"
                         x-data="{ tags: @js($nicheTags), input: '',
                                   add() {
                                       const v = this.input.trim().toLowerCase();
                                       if (!v) return;
                                       if (this.tags.includes(v)) { this.input=''; return; }
                                       if (this.tags.length >= 8) return;
                                       this.tags.push(v);
                                       this.input = '';
                                   },
                                   remove(t) { this.tags = this.tags.filter(x => x !== t); } }">
                        <template x-for="t in tags" :key="t">
                            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-blue-50 text-blue-700 font-semibold">
                                #<span x-text="t"></span>
                                <button type="button" @click="remove(t)" class="text-blue-500 hover:text-rose-600">&times;</button>
                                <input type="hidden" name="niche_tags[]" :value="t">
                            </span>
                        </template>
                        <input type="text" x-model="input" @keydown.enter.prevent="add()" @keydown.,.prevent="add()"
                               placeholder="add tag, press enter" maxlength="32"
                               class="flex-1 min-w-[140px] px-2 py-1 rounded text-xs" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                </div>
            </div>
        </fieldset>

        {{-- Socials --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Socials</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($platforms as $key => $p)
                    <div>
                        <label class="text-xs font-semibold mb-1 flex items-center gap-1" style="color: var(--text-dimmed);">
                            <i class="{{ $p['icon'] }}"></i> {{ $p['label'] }}
                        </label>
                        <input type="text" name="socials[{{ $key }}]"
                               value="{{ old('socials.' . $key, $socials[$key] ?? '') }}"
                               placeholder="{{ $p['placeholder'] }}" maxlength="200"
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                @endforeach
            </div>
        </fieldset>

        {{-- Section visibility --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Sections shown</legend>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Toggle the chunks you want on your public page.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($sectionDefaults as $key => $default)
                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft);">
                        <input type="hidden" name="sections[{{ $key }}]" value="0">
                        <input type="checkbox" name="sections[{{ $key }}]" value="1"
                               {{ ($sections[$key] ?? $default) ? 'checked' : '' }}
                               class="rounded text-blue-600 focus:ring-blue-500" style="border-color: var(--border-glass);">
                        <span class="text-xs font-semibold capitalize" style="color: var(--text-primary);">{{ $key }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- Organizer profile (Task #3699) — account-wide event organizer
             details shown on the public event detail page and on
             /@handle/events. Not per-event; there is one profile per
             account. --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Organizer profile</legend>
            <p class="text-[11px] mb-3" style="color: var(--text-dimmed);">Shown on all of your events, the public event page and your events listing. Everything here is optional; blank fields simply don't render.</p>

            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Organizer logo</label>
                    @if($organizer['logo'])
                        <div class="mb-2 relative inline-block">
                            <img src="{{ $organizer['logo'] }}" class="w-16 h-16 object-cover rounded-lg border" style="border-color: var(--border-soft);">
                            <label class="absolute -top-2 -right-2 text-[11px] px-2 py-0.5 rounded bg-white/90 text-slate-700 cursor-pointer">
                                <input type="checkbox" name="organizer_logo_remove" value="1" class="mr-1"> Remove
                            </label>
                        </div>
                    @else
                        <div class="mb-2 inline-block">
                            <img src="{{ asset('images/events/host-avatar-placeholder.svg') }}" alt="" class="w-16 h-16 object-cover rounded-lg border" style="border-color: var(--border-soft);">
                        </div>
                    @endif
                    <input type="file" name="organizer_logo" accept="image/*" class="text-xs block">
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Or paste a URL:</p>
                    <input type="url" name="organizer_logo_url" placeholder="https://…" maxlength="1024"
                           class="w-full px-3 py-2 rounded-lg text-sm mt-1" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Organizer / business name</label>
                        <input type="text" name="organizer_name" value="{{ old('organizer_name', $organizer['name']) }}" maxlength="150" placeholder="e.g. Acme Events Co."
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Website</label>
                        <input type="url" name="organizer_website" value="{{ old('organizer_website', $organizer['website']) }}" maxlength="1024" placeholder="https://…"
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Short description</label>
                    <textarea name="organizer_description" rows="2" maxlength="1000" placeholder="A line or two about who's hosting."
                              class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">{{ old('organizer_description', $organizer['description']) }}</textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Contact person</label>
                        <input type="text" name="organizer_contact_name" value="{{ old('organizer_contact_name', $organizer['contact_name']) }}" maxlength="150" placeholder="Name"
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Contact phone</label>
                        <input type="text" name="organizer_contact_phone" value="{{ old('organizer_contact_phone', $organizer['contact_phone']) }}" maxlength="40" placeholder="+1 555 123 4567"
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Contact email</label>
                        <input type="email" name="organizer_contact_email" value="{{ old('organizer_contact_email', $organizer['contact_email']) }}" maxlength="255" placeholder="events@yourdomain.com"
                               class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Address</label>
                    <input type="text" name="organizer_address" value="{{ old('organizer_address', $organizer['address']) }}" maxlength="500" placeholder="Street, City, Country"
                           class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-semibold mb-2 block" style="color: var(--text-dimmed);">Organizer socials</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($platforms as $key => $p)
                            <div>
                                <label class="text-xs font-semibold mb-1 flex items-center gap-1" style="color: var(--text-dimmed);">
                                    <i class="{{ $p['icon'] }}"></i> {{ $p['label'] }}
                                </label>
                                <input type="text" name="organizer_socials[{{ $key }}]"
                                       value="{{ old('organizer_socials.' . $key, $organizer['socials'][$key] ?? '') }}"
                                       placeholder="{{ $p['placeholder'] }}" maxlength="200"
                                       class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </fieldset>

        {{-- Task #1211 — Safety & moderation --}}
        @php
            $wm = is_array($user->watermark_settings) ? $user->watermark_settings : [];
            $wmEnabled  = (bool) ($wm['enabled'] ?? false);
            $wmOpacity  = (int)  ($wm['opacity'] ?? 35);
            $wmPosition = (string) ($wm['position'] ?? 'br');
            $wmTpl      = (string) ($wm['text_template'] ?? '@{handle} • {viewer}');
            $muteWords  = is_array($user->mute_words) ? implode(', ', $user->mute_words) : '';
            $cBlock     = is_array($user->country_block_list) ? implode(', ', $user->country_block_list) : '';
            $cAllow     = is_array($user->country_allow_list) ? implode(', ', $user->country_allow_list) : '';
        @endphp
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Safety &amp; moderation</legend>
            <p class="text-[11px] mb-3" style="color: var(--text-dimmed);">Mute words, watermarking on shared images, and per-region availability.</p>

            <div class="space-y-4">
                {{-- Mute words --}}
                <div>
                    <label class="text-xs font-semibold" style="color: var(--text-primary);">Mute words on your comments</label>
                    <textarea name="mute_words_text" rows="2" placeholder="slur1, slur2, scammer"
                              class="mt-1 w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">{{ old('mute_words_text', $muteWords) }}</textarea>
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Comma- or newline-separated. Matched comments are silently hidden, admins still see them.</p>
                </div>

                {{-- Watermarking --}}
                <div class="rounded-lg p-3 border" style="border-color: var(--border-glass);">
                    <label class="flex items-start gap-2">
                        <input type="hidden" name="watermark_enabled" value="0">
                        <input type="checkbox" name="watermark_enabled" value="1" {{ old('watermark_enabled', $wmEnabled) ? 'checked' : '' }}
                               class="mt-0.5 rounded text-blue-600" style="border-color: var(--border-glass);">
                        <span>
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">Watermark images with viewer's name</span>
                            <span class="block text-[11px]" style="color: var(--text-dimmed);">Adds "@your-handle • @their-handle" to every image so screenshots are traceable.</span>
                        </span>
                    </label>
                    <div class="grid grid-cols-3 gap-2 mt-3 text-xs">
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Opacity</label>
                            <input type="number" name="watermark_opacity" min="10" max="90" value="{{ old('watermark_opacity', $wmOpacity) }}"
                                   class="mt-1 w-full px-2 py-1.5 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"/>
                        </div>
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Position</label>
                            <select name="watermark_position" class="mt-1 w-full px-2 py-1.5 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                                @foreach(['tl' => 'Top left', 'tr' => 'Top right', 'bl' => 'Bottom left', 'br' => 'Bottom right', 'center' => 'Centre'] as $k => $label)
                                    <option value="{{ $k }}" {{ old('watermark_position', $wmPosition) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Template</label>
                            <input name="watermark_text_template" maxlength="120" value="{{ old('watermark_text_template', $wmTpl) }}"
                                   class="mt-1 w-full px-2 py-1.5 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"/>
                        </div>
                    </div>
                </div>

                {{-- Country gating --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold" style="color: var(--text-primary);">Block from countries</label>
                        <input name="country_block_text" value="{{ old('country_block_text', $cBlock) }}"
                               placeholder="US, GB, DE"
                               class="mt-1 w-full px-3 py-2 rounded-lg border text-sm uppercase" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"/>
                        <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">2-letter ISO codes. Leave empty for "everywhere".</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color: var(--text-primary);">Allow only from countries</label>
                        <input name="country_allow_text" value="{{ old('country_allow_text', $cAllow) }}"
                               placeholder="US, CA"
                               class="mt-1 w-full px-3 py-2 rounded-lg border text-sm uppercase" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"/>
                        <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">When set, every other country is blocked. Allow wins over block.</p>
                    </div>
                </div>

                {{-- DMCA contact --}}
                <div>
                    <label class="text-xs font-semibold" style="color: var(--text-primary);">DMCA contact email</label>
                    <input type="email" name="dmca_email" maxlength="255" value="{{ old('dmca_email', $user->dmca_email) }}"
                           placeholder="legal@yourdomain.com"
                           class="mt-1 w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);"/>
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Used when admins forward you a takedown notice. Defaults to your account email.</p>
                </div>

                <div class="flex justify-end">
                    {{-- Submits the standalone #cp-digest-sample-form declared after the
                         main form. A nested <form> here is invalid HTML: the browser
                         closes the OUTER form at the inner </form>, dumping every later
                         fieldset + the save bar out of the form grid (broke the row
                         gaps and the sticky save row). --}}
                    <button type="submit" form="cp-digest-sample-form" onclick="event.stopPropagation()"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-1"></i> Send me a sample weekly digest
                    </button>
                </div>
            </div>
        </fieldset>

        {{-- ── Task #5459: Featured Links ──────────────────────── --}}
        @php
            $__flPreviewStyle = [
                'classic'      => 'background:#fff;border:1px solid rgba(61,107,255,.3);border-radius:6px;padding:4px 6px;color:#1e293b;text-align:left;',
                'outline'      => 'border:2px solid #3d6bff;border-radius:6px;padding:3px 6px;color:#3d6bff;text-align:center;background:transparent;',
                'solid'        => 'background:#3d6bff;border-radius:6px;padding:4px 6px;color:#fff;text-align:center;',
                'ghost'        => 'color:#3d6bff;padding:4px 2px;text-align:left;text-decoration:underline;background:transparent;',
                'pill'         => 'background:#3d6bff;border-radius:9999px;padding:4px 10px;color:#fff;text-align:center;',
                'card_heading' => 'background:#fff;border-left:3px solid #3d6bff;border-radius:0 6px 6px 0;padding:4px 6px;color:#3d6bff;text-align:left;',
            ];
        @endphp
        <fieldset class="rounded-2xl px-5 pt-3 pb-5 lg:col-span-2" style="background: var(--bg-card); border: 1px solid var(--border-soft);"
                  x-data="{
                      featured: {{ Js::from($showcaseFeaturedLinks) }},
                      style: {{ Js::from($featuredLinksStyle) }},
                      maxFeatured: 8,
                      linkMap: {{ Js::from($pickerLinkMap) }},
                      dragIdx: -1,
                      dragOverIdx: -1,
                      dragStart(idx) { this.dragIdx = idx; },
                      dragDrop(idx) {
                          if (this.dragIdx < 0 || this.dragIdx === idx) { this.dragIdx = -1; this.dragOverIdx = -1; return; }
                          const item = this.featured.splice(this.dragIdx, 1)[0];
                          this.featured.splice(idx, 0, item);
                          this.dragIdx = -1; this.dragOverIdx = -1;
                      },
                      addFeatured(id) {
                          id = parseInt(id);
                          if (!id || this.featured.some(x => x.id === id)) return;
                          if (this.featured.length >= this.maxFeatured) return;
                          this.featured.push({ id: id, enabled: true });
                      }
                  }">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">
                <i class="fas fa-star mr-1 text-amber-500"></i> Featured links
            </legend>
            <p class="text-xs mb-4" style="color: var(--text-dimmed);">Pin up to 8 links to your public profile. Choose a shared style, drag to reorder, and toggle each link on or off.</p>

            {{-- ── Style picker ──────────────────────────────── --}}
            <div class="mb-4">
                <label class="text-xs font-semibold block mb-2" style="color: var(--text-primary);">Link display style</label>
                <input type="hidden" name="featured_links_style" :value="style">
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                    @foreach($featuredLinkStyles as $sKey => $sLabel)
                        <button type="button" @click="style = '{{ $sKey }}'"
                                :class="style === '{{ $sKey }}' ? 'ring-2 ring-blue-500' : ''"
                                class="rounded-xl p-2 text-center cursor-pointer transition-opacity"
                                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <div class="mb-1 pointer-events-none"
                                 style="font-size:9px;font-weight:700;line-height:1.4;white-space:nowrap;overflow:hidden;{{ $__flPreviewStyle[$sKey] ?? '' }}">My&nbsp;link</div>
                            <span class="text-[9px]" style="color: var(--text-dimmed);">{{ $sLabel }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ── Drag-to-reorder list with enable toggles ──── --}}
            <div class="space-y-1.5 mb-3">
                <template x-for="(item, idx) in featured" :key="item.id">
                    <div draggable="true"
                         @dragstart="dragStart(idx)"
                         @dragover.prevent="dragOverIdx = idx"
                         @dragleave="dragOverIdx = -1"
                         @drop.prevent="dragDrop(idx)"
                         :class="dragOverIdx === idx && dragIdx !== idx ? 'opacity-40' : ''"
                         class="flex items-center gap-2 px-3 py-2 rounded-lg select-none"
                         style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); cursor: grab; transition: opacity .1s;">
                        <i class="fas fa-grip-lines text-xs shrink-0" style="color: var(--text-dimmed);"></i>
                        {{-- Visibility toggle --}}
                        <button type="button"
                                @click="item.enabled = !item.enabled"
                                :title="item.enabled ? 'Click to hide' : 'Click to show'"
                                class="shrink-0 text-sm"
                                :style="item.enabled ? 'color:var(--color-primary,#3d6bff)' : 'color:var(--text-dimmed)'">
                            <i :class="item.enabled ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
                        </button>
                        <span class="flex-1 text-sm truncate"
                              :style="item.enabled ? 'color:var(--text-primary)' : 'color:var(--text-dimmed);text-decoration:line-through;'"
                              x-text="(linkMap[item.id] || {}).title || ('#' + item.id)"></span>
                        <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded-full shrink-0"
                              style="background: rgba(61,107,255,0.08); color: #3d6bff;"
                              x-text="(linkMap[item.id] || {}).type || ''"></span>
                        <button type="button" @click="featured.splice(idx, 1)"
                                class="shrink-0 text-xs hover:text-rose-500" style="color: var(--text-dimmed);">
                            <i class="fas fa-times"></i>
                        </button>
                        {{-- Serialise row to POST --}}
                        <input type="hidden" :name="'featured_links['+idx+'][id]'" :value="item.id">
                        <input type="hidden" :name="'featured_links['+idx+'][enabled]'" :value="item.enabled ? '1' : '0'">
                    </div>
                </template>
            </div>

            {{-- ── Add link picker ───────────────────────────── --}}
            <div class="flex items-center gap-2" x-show="featured.length < maxFeatured">
                <select id="featured-add-picker"
                        class="flex-1 px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                    <option value="">— Add a link —</option>
                    @foreach($pickerLinks as $pl)
                        <option value="{{ $pl->id }}">{{ $pl->title ?: $pl->alias }} ({{ $pl->type }})</option>
                    @endforeach
                </select>
                <button type="button"
                        @click="addFeatured(document.getElementById('featured-add-picker').value); document.getElementById('featured-add-picker').value=''"
                        class="px-3 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 shrink-0">
                    Add
                </button>
            </div>

            <label class="flex items-center gap-2 mt-3 cursor-pointer">
                <input type="hidden" name="showcase_show_link_stats" value="0">
                <input type="checkbox" name="showcase_show_link_stats" value="1"
                       {{ ($showcase['show_link_stats'] ?? false) ? 'checked' : '' }}
                       class="rounded text-blue-600" style="border-color: var(--border-glass);">
                <span class="text-sm" style="color: var(--text-primary);">Show click counts on featured link cards</span>
            </label>

            {{-- Section visibility toggle --}}
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="sections[featured_links]" value="0">
                    <input type="checkbox" name="sections[featured_links]" value="1"
                           {{ ($sections['featured_links'] ?? true) ? 'checked' : '' }}
                           class="rounded text-blue-600" style="border-color: var(--border-glass);">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Show featured links section on my profile</span>
                </label>
            </div>
        </fieldset>

        {{-- ── Task #5431: Showcase ─────────────────────────────── --}}
        <fieldset class="rounded-2xl px-5 pt-3 pb-5 lg:col-span-2" style="background: var(--bg-card); border: 1px solid var(--border-soft);"
                  x-data="{
                      items: {{ Js::from(array_values($showcase['showcase_items'] ?? [])) }},
                      showcaseTypes: {{ Js::from($showcaseItemTypes) }},
                      pickType: '',
                      pickLink: '',
                      addItem() {
                          if (!this.pickType || !this.pickLink) return;
                          const id = parseInt(this.pickLink);
                          if (!id) return;
                          if (this.items.some(i => i.link_id === id && i.type === this.pickType)) return;
                          if (this.items.length >= 20) return;
                          this.items.push({ type: this.pickType, link_id: id });
                          this.pickType = ''; this.pickLink = '';
                      },
                      removeItem(idx) { this.items.splice(idx, 1); }
                  }">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">
                <i class="fas fa-grid-2 mr-1 text-fuchsia-500"></i> Showcase
            </legend>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Spotlight your best creations — QR codes, forms, events, digital cards, menus, and more.</p>

            {{-- Hidden inputs for saved items --}}
            <template x-for="(item, idx) in items" :key="idx">
                <span>
                    <input type="hidden" :name="'showcase_items['+idx+'][type]'" :value="item.type">
                    <input type="hidden" :name="'showcase_items['+idx+'][link_id]'" :value="item.link_id">
                </span>
            </template>

            {{-- Current items --}}
            <div class="space-y-1.5 mb-3">
                @php
                    $scLinkMap = $showcaseEligibleLinks->keyBy('id')->map(fn($l) => [
                        'title' => $l->title ?: $l->alias,
                        'type'  => $l->type,
                    ])->toArray();
                @endphp
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <span class="text-sm shrink-0" style="color: var(--text-dimmed);"
                              x-html="'<i class=\'' + (showcaseTypes[item.type] || {}).icon + '\'></i>'"></span>
                        <span class="flex-1 text-sm truncate" style="color: var(--text-primary);"
                              x-text="(@js($scLinkMap)[item.link_id] || {}).title || ('#' + item.link_id)"></span>
                        <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded-full"
                              style="background: rgba(147,51,234,0.08); color: #9333ea;"
                              x-text="(showcaseTypes[item.type] || {}).label || item.type"></span>
                        <button type="button" @click="removeItem(idx)"
                                class="text-xs hover:text-rose-500" style="color: var(--text-dimmed);">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </template>
            </div>

            {{-- Picker row --}}
            <div class="flex items-center gap-2 flex-wrap" x-show="items.length < 20">
                <select x-model="pickType"
                        class="px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                    <option value="">— Type —</option>
                    @foreach($showcaseItemTypes as $typeKey => $typeMeta)
                        <option value="{{ $typeKey }}">{{ $typeMeta['label'] }}</option>
                    @endforeach
                </select>
                <select x-model="pickLink"
                        class="flex-1 min-w-[140px] px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                    <option value="">— Link —</option>
                    @foreach($showcaseEligibleLinks as $sel)
                        <option value="{{ $sel->id }}">{{ $sel->title ?: $sel->alias }} ({{ $sel->type }})</option>
                    @endforeach
                </select>
                <button type="button" @click="addItem"
                        class="px-3 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 shrink-0">
                    Add
                </button>
            </div>

            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="sections[showcase]" value="0">
                    <input type="checkbox" name="sections[showcase]" value="1"
                           {{ ($sections['showcase'] ?? true) ? 'checked' : '' }}
                           class="rounded text-blue-600" style="border-color: var(--border-glass);">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Show showcase section on my profile</span>
                </label>
            </div>
        </fieldset>

        {{-- ── Task #5431: Highlights strip ────────────────────── --}}
        <fieldset class="rounded-2xl px-5 pt-3 pb-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-1 text-sky-500"></i> Highlights strip
            </legend>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Pick which metrics to show below your hero image.</p>
            <div class="space-y-2">
                @foreach([
                    'highlights_show_followers'    => ['label' => 'Follower count', 'key' => 'show_followers'],
                    'highlights_show_links'        => ['label' => 'Public link count', 'key' => 'show_links'],
                    'highlights_show_member_since' => ['label' => 'Member since (year)', 'key' => 'show_member_since'],
                    'highlights_show_verified'     => ['label' => 'Verified badge', 'key' => 'show_verified'],
                ] as $fieldName => $hlMeta)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="{{ $fieldName }}" value="0">
                        <input type="checkbox" name="{{ $fieldName }}" value="1"
                               {{ ($showcase['highlights'][$hlMeta['key']] ?? true) ? 'checked' : '' }}
                               class="rounded text-blue-600" style="border-color: var(--border-glass);">
                        <span class="text-sm" style="color: var(--text-primary);">{{ $hlMeta['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="sections[highlights]" value="0">
                    <input type="checkbox" name="sections[highlights]" value="1"
                           {{ ($sections['highlights'] ?? true) ? 'checked' : '' }}
                           class="rounded text-blue-600" style="border-color: var(--border-glass);">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Show highlights strip on my profile</span>
                </label>
            </div>
        </fieldset>

        {{-- ── Task #5431: CTA / Contact block ─────────────────── --}}
        <fieldset class="rounded-2xl px-5 pt-3 pb-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);"
                  x-data="{
                      primary: {{ Js::from($showcase['cta']['primary'] ?? null) }},
                      secondary: {{ Js::from(array_values($showcase['cta']['secondary'] ?? [])) }},
                      ctaKinds: {{ Js::from($ctaKinds) }},
                      addSecondary() {
                          if (this.secondary.length >= 3) return;
                          this.secondary.push({ kind: 'link', label: '', value: '' });
                      },
                      removeSecondary(idx) { this.secondary.splice(idx, 1); },
                      setPrimary(kind) {
                          if (!this.primary) this.primary = { kind, label: '', value: '' };
                          else this.primary.kind = kind;
                      }
                  }">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">
                <i class="fas fa-hand-pointer mr-1 text-rose-500"></i> CTA / Contact
            </legend>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Add call-to-action buttons to your profile (email, WhatsApp, external link, etc.).</p>

            {{-- Primary CTA --}}
            <div class="mb-4">
                <label class="text-xs font-semibold block mb-1" style="color: var(--text-primary);">Primary button</label>
                <div class="flex gap-2 flex-wrap">
                    <select name="cta_primary_kind"
                            x-model="primary && primary.kind"
                            @change="setPrimary($event.target.value)"
                            class="w-40 px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                        <option value="">— None —</option>
                        @foreach($ctaKinds as $ck => $cv)
                            <option value="{{ $ck }}" {{ ($showcase['cta']['primary']['kind'] ?? '') === $ck ? 'selected' : '' }}>{{ $cv['label'] }}</option>
                        @endforeach
                    </select>
                    <template x-if="primary">
                        <input type="text" name="cta_primary_label" maxlength="80"
                               x-model="primary.label"
                               placeholder="Button label"
                               class="flex-1 min-w-[100px] px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                    </template>
                </div>
                <template x-if="primary && primary.kind !== 'form'">
                    <input type="text" name="cta_primary_value" maxlength="500"
                           x-model="primary.value"
                           :placeholder="(ctaKinds[primary.kind] || {}).hint || 'Value'"
                           class="mt-1.5 w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                </template>
                <template x-if="primary && primary.kind === 'form'">
                    <select name="cta_primary_value"
                            x-model="primary.value"
                            class="mt-1.5 w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                        <option value="">— Choose a form —</option>
                        @foreach($formsForCta as $cf)
                            <option value="{{ $cf->alias }}" {{ ($showcase['cta']['primary']['value'] ?? '') === $cf->alias ? 'selected' : '' }}>{{ $cf->title ?: $cf->alias }}</option>
                        @endforeach
                    </select>
                </template>
            </div>

            {{-- Secondary CTAs --}}
            <div class="mb-3">
                <label class="text-xs font-semibold block mb-2" style="color: var(--text-primary);">Secondary buttons (up to 3)</label>
                <div class="space-y-2">
                    <template x-for="(sec, idx) in secondary" :key="idx">
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- Hidden inputs --}}
                            <input type="hidden" :name="'cta_secondary['+idx+'][kind]'" :value="sec.kind">
                            <input type="hidden" :name="'cta_secondary['+idx+'][label]'" :value="sec.label">
                            <input type="hidden" :name="'cta_secondary['+idx+'][value]'" :value="sec.value">

                            <select x-model="sec.kind"
                                    class="px-2 py-1.5 rounded-lg border text-xs w-36" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                                @foreach($ctaKinds as $ck => $cv)
                                    <option value="{{ $ck }}">{{ $cv['label'] }}</option>
                                @endforeach
                            </select>
                            <input type="text" x-model="sec.label" placeholder="Label" maxlength="80"
                                   class="w-28 px-2 py-1.5 rounded-lg border text-xs" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                            <input type="text" x-model="sec.value" placeholder="Value" maxlength="500"
                                   class="flex-1 min-w-[80px] px-2 py-1.5 rounded-lg border text-xs" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                            <button type="button" @click="removeSecondary(idx)"
                                    class="text-xs hover:text-rose-500" style="color: var(--text-dimmed);">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addSecondary" x-show="secondary.length < 3"
                        class="mt-2 text-xs font-semibold px-3 py-1.5 rounded-lg"
                        style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                    <i class="fas fa-plus mr-1"></i> Add secondary button
                </button>
            </div>

            <div class="mt-3 pt-3 border-t" style="border-color: var(--border-glass);">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="sections[cta]" value="0">
                    <input type="checkbox" name="sections[cta]" value="1"
                           {{ ($sections['cta'] ?? true) ? 'checked' : '' }}
                           class="rounded text-blue-600" style="border-color: var(--border-glass);">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Show CTA block on my profile</span>
                </label>
            </div>
        </fieldset>

        {{-- Publish --}}
        <fieldset class="rounded-2xl px-5 pt-3 pb-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Publish</legend>
            <label class="flex items-start gap-3 p-2.5 rounded-lg cursor-pointer" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft);">
                <input type="hidden" name="profile_published" value="0">
                <input type="checkbox" name="profile_published" value="1"
                       {{ $user->profile_published ? 'checked' : '' }}
                       class="mt-0.5 rounded text-blue-600 focus:ring-blue-500" style="border-color: var(--border-glass);">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--text-primary);">My profile is live at /{{ '@' . ($user->handle ?: 'handle') }}</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-dimmed);">When off, only you can see it. (You'll need a handle first.)</p>
                </div>
            </label>
        </fieldset>

        <div class="flex justify-end items-center gap-2 lg:col-span-2 sticky bottom-0 z-40 rounded-xl px-4 py-3 -mx-1"
             style="background: color-mix(in srgb, var(--bg-card) 82%, transparent); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--border-soft); box-shadow: 0 -6px 24px rgba(0,0,0,0.18); margin-top: -1.5rem;">
            <a href="{{ route('user.posts.index') }}" class="text-xs font-semibold px-4 py-2 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-soft); color: var(--text-primary);">
                Manage posts
            </a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Save profile
            </button>
        </div>
    </form>

    {{-- Standalone target for the "Send me a sample weekly digest" button
         (linked via the button's form="cp-digest-sample-form" attribute).
         Must live OUTSIDE the main profile form — see the nested-form note
         at the button. --}}
    <form id="cp-digest-sample-form" method="POST" action="{{ route('user.creator-digest.sample') }}">
        @csrf
    </form>
    </div>{{-- /left column --}}

    {{-- ── Live preview pane ────────────────────────────── --}}
    <aside class="hidden xl:block shrink-0 sticky top-24 transition-all duration-300"
           :style="'width:' + pvPaneW + 'px'">
        <div class="rounded-2xl overflow-hidden" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <div class="flex items-center justify-between gap-2 px-3 py-2" style="border-bottom: 1px solid var(--border-glass);">
                <p class="text-[11px] uppercase tracking-wider font-semibold truncate" style="color: var(--text-dimmed);">
                    <i class="fas fa-eye mr-1"></i><span x-show="pvMode !== 'mini'">Live preview</span>
                </p>
                <div class="flex items-center gap-1">
                    <template x-for="m in [['mini','Mini'],['small','Small'],['large','Large']]" :key="m[0]">
                        <button type="button" @click="setMode(m[0])"
                                class="text-[10px] font-semibold px-2 py-1 rounded-md"
                                :style="pvMode === m[0]
                                    ? 'background: rgba(61,107,255,0.15); color: var(--color-primary-500, #3d6bff);'
                                    : 'color: var(--text-dimmed);'"
                                x-text="m[1]"></button>
                    </template>
                    @if($profileUrl)
                    <button type="button" @click="setMode('full')" title="Full preview"
                            class="text-[10px] font-semibold px-2 py-1 rounded-md" style="color: var(--text-dimmed);">
                        <i class="fas fa-expand"></i>
                    </button>
                        <button type="button" @click="pvReload()" title="Refresh preview"
                                class="text-[10px] font-semibold px-2 py-1 rounded-md" style="color: var(--text-dimmed);">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    @endif
                </div>
            </div>
            @if($profileUrl)
                <div class="p-3">
                    <div class="rounded-xl overflow-hidden mx-auto" style="border: 1px solid var(--border-glass);"
                         :style="'width:' + (pvBase * pvScale) + 'px; height:' + (pvBaseH * pvScale) + 'px;'">
                        <iframe x-ref="pvFrame" src="{{ $profileUrl }}?cp_preview=1" title="Profile preview" loading="lazy" @load="pvLive()"
                                style="width: 390px; height: 760px; transform-origin: top left; border: 0; pointer-events: none;"
                                :style="'transform: scale(' + pvScale + ');'"></iframe>
                    </div>
                    <p class="text-[10px] mt-2 text-center" style="color: var(--text-dimmed);" x-show="pvMode !== 'mini'">
                        Save the form, then hit <i class="fas fa-rotate-right"></i> to refresh.
                    </p>
                </div>
            @else
                <div class="p-4 text-center">
                    <i class="fas fa-id-badge text-2xl mb-2" style="color: var(--text-dimmed);"></i>
                    <p class="text-xs" style="color: var(--text-dimmed);">Claim your handle above to see a live preview of your public profile here.</p>
                </div>
            @endif
        </div>
    </aside>

    {{-- ── Full-screen preview overlay ──────────────────── --}}
    @if($profileUrl)
        <div x-show="pvMode === 'full'" x-cloak class="fixed inset-0 z-[90] flex flex-col"
                 style="background: rgba(10,12,24,0.85); backdrop-filter: blur(6px);"
                 @keydown.escape.window="if (pvMode === 'full') setMode('small')">
                <div class="flex items-center justify-between px-4 py-3">
                    <p class="text-sm font-bold text-white"><i class="fas fa-eye mr-2"></i>Profile preview — {{ '/@' . $user->handle }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="pvReload()" class="text-xs font-semibold px-3 py-2 rounded-lg text-white" style="background: rgba(255,255,255,0.12);">
                            <i class="fas fa-rotate-right mr-1"></i> Refresh
                        </button>
                        <a href="{{ $profileUrl }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-2 rounded-lg text-white" style="background: rgba(255,255,255,0.12);">
                            <i class="fas fa-up-right-from-square mr-1"></i> Open
                        </a>
                        <button type="button" @click="setMode('small')" class="text-xs font-semibold px-3 py-2 rounded-lg text-white" style="background: rgba(255,255,255,0.12);">
                            <i class="fas fa-xmark mr-1"></i> Close
                        </button>
                    </div>
                </div>
                <div class="flex-1 px-4 pb-4 min-h-0">
                    <template x-if="pvMode === 'full'">
                        <iframe x-ref="pvFrameFull" src="{{ $profileUrl }}?cp_preview=1" title="Profile preview (full)" @load="pvLive()"
                                class="w-full h-full rounded-xl bg-white" style="border: 0;"></iframe>
                    </template>
                </div>
            </div>
    @endif
    </div>{{-- /xl:flex --}}
</div>
@endsection
