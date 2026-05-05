@extends('user.layouts.app')
@section('title', 'Creator Profile')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Creator Profile</h1>
            <p class="text-sm mt-1" style="color: var(--text-dimmed);">Your public page at <code>/@{{ $user->handle ?? 'handle' }}</code></p>
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

    {{-- ── Completeness meter ───────────────────────────── --}}
    <div class="rounded-2xl p-5 mb-6" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-dimmed);">Profile completeness</p>
            <p class="text-sm font-bold" style="color: var(--text-primary);">{{ $completeness }}%</p>
        </div>
        <div class="h-2 w-full rounded-full overflow-hidden" style="background: rgba(124,58,237,0.1);">
            <div class="h-full bg-gradient-to-r from-violet-500 to-fuchsia-500 transition-all" style="width: {{ $completeness }}%;"></div>
        </div>
        <p class="text-[11px] mt-2" style="color: var(--text-dimmed);">Add a cover, tagline, niche tags, socials, and your first post to reach 100%.</p>
    </div>

    {{-- ── Handle claim ─────────────────────────────────── --}}
    @if(empty($user->handle))
        <div class="rounded-2xl p-5 mb-6 border-l-4 border-violet-500" style="background: rgba(124,58,237,0.04);">
            <p class="text-sm font-bold mb-2" style="color: var(--text-primary);">Claim your handle</p>
            <p class="text-xs mb-3" style="color: var(--text-dimmed);">Your profile lives at <code>/@yourname</code>. Pick a 3–30 character handle (letters, numbers, underscore).</p>
            <form action="{{ route('user.creator-profile.handle.claim') }}" method="POST" class="flex gap-2 items-center">
                @csrf
                <span class="text-sm font-bold" style="color: var(--text-primary);">@</span>
                <input type="text" name="handle" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+"
                       placeholder="yourname"
                       class="flex-1 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                <button class="px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">Claim</button>
            </form>
        </div>
    @endif

    {{-- ── Main editor ──────────────────────────────────── --}}
    <form action="{{ route('user.creator-profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Hero --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Hero</legend>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold mb-1 block" style="color: var(--text-dimmed);">Cover image</label>
                    @if($user->cover_image)
                        <div class="mb-2 relative">
                            <img src="{{ $user->cover_image }}" class="w-full h-32 object-cover rounded-lg">
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
                            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-violet-50 text-violet-700 font-semibold">
                                #<span x-text="t"></span>
                                <button type="button" @click="remove(t)" class="text-violet-500 hover:text-rose-600">&times;</button>
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
                               class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        <span class="text-xs font-semibold capitalize" style="color: var(--text-primary);">{{ $key }}</span>
                    </label>
                @endforeach
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
                              class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">{{ old('mute_words_text', $muteWords) }}</textarea>
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Comma- or newline-separated. Matched comments are silently hidden — admins still see them.</p>
                </div>

                {{-- Watermarking --}}
                <div class="rounded-lg p-3 border border-slate-200">
                    <label class="flex items-start gap-2">
                        <input type="hidden" name="watermark_enabled" value="0">
                        <input type="checkbox" name="watermark_enabled" value="1" {{ old('watermark_enabled', $wmEnabled) ? 'checked' : '' }}
                               class="mt-0.5 rounded border-slate-300 text-violet-600">
                        <span>
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">Watermark images with viewer's name</span>
                            <span class="block text-[11px]" style="color: var(--text-dimmed);">Adds "@your-handle • @their-handle" to every image so screenshots are traceable.</span>
                        </span>
                    </label>
                    <div class="grid grid-cols-3 gap-2 mt-3 text-xs">
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Opacity</label>
                            <input type="number" name="watermark_opacity" min="10" max="90" value="{{ old('watermark_opacity', $wmOpacity) }}"
                                   class="mt-1 w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"/>
                        </div>
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Position</label>
                            <select name="watermark_position" class="mt-1 w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                                @foreach(['tl' => 'Top left', 'tr' => 'Top right', 'bl' => 'Bottom left', 'br' => 'Bottom right', 'center' => 'Centre'] as $k => $label)
                                    <option value="{{ $k }}" {{ old('watermark_position', $wmPosition) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-semibold block" style="color: var(--text-primary);">Template</label>
                            <input name="watermark_text_template" maxlength="120" value="{{ old('watermark_text_template', $wmTpl) }}"
                                   class="mt-1 w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"/>
                        </div>
                    </div>
                </div>

                {{-- Country gating --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold" style="color: var(--text-primary);">Block from countries</label>
                        <input name="country_block_text" value="{{ old('country_block_text', $cBlock) }}"
                               placeholder="US, GB, DE"
                               class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm uppercase"/>
                        <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">2-letter ISO codes. Leave empty for "everywhere".</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color: var(--text-primary);">Allow only from countries</label>
                        <input name="country_allow_text" value="{{ old('country_allow_text', $cAllow) }}"
                               placeholder="US, CA"
                               class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm uppercase"/>
                        <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">When set, every other country is blocked. Allow wins over block.</p>
                    </div>
                </div>

                {{-- DMCA contact --}}
                <div>
                    <label class="text-xs font-semibold" style="color: var(--text-primary);">DMCA contact email</label>
                    <input type="email" name="dmca_email" maxlength="255" value="{{ old('dmca_email', $user->dmca_email) }}"
                           placeholder="legal@yourdomain.com"
                           class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"/>
                    <p class="text-[11px] mt-1" style="color: var(--text-dimmed);">Used when admins forward you a takedown notice. Defaults to your account email.</p>
                </div>

                <div class="flex justify-end">
                    <form method="POST" action="{{ route('user.creator-digest.sample') }}" onclick="event.stopPropagation()">
                        @csrf
                        <button class="text-xs font-semibold px-3 py-1.5 rounded-lg" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft); color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-1"></i> Send me a sample weekly digest
                        </button>
                    </form>
                </div>
            </div>
        </fieldset>

        {{-- Publish --}}
        <fieldset class="rounded-2xl p-5" style="background: var(--bg-card); border: 1px solid var(--border-soft);">
            <legend class="text-sm font-bold px-2" style="color: var(--text-primary);">Publish</legend>
            <label class="flex items-start gap-3 p-3 rounded-lg cursor-pointer" style="background: var(--bg-input, #fff); border: 1px solid var(--border-soft);">
                <input type="hidden" name="profile_published" value="0">
                <input type="checkbox" name="profile_published" value="1"
                       {{ $user->profile_published ? 'checked' : '' }}
                       class="mt-0.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                <div>
                    <p class="text-sm font-semibold" style="color: var(--text-primary);">My profile is live at /@{{ $user->handle ?: 'handle' }}</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-dimmed);">When off, only you can see it. (You'll need a handle first.)</p>
                </div>
            </label>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.posts.index') }}" class="text-xs font-semibold px-4 py-2 rounded-lg" style="background: var(--bg-card); border: 1px solid var(--border-soft); color: var(--text-primary);">
                Manage posts
            </a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-bold hover:bg-violet-700">
                <i class="fas fa-save mr-1"></i> Save profile
            </button>
        </div>
    </form>
</div>
@endsection
