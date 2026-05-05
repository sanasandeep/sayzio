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
