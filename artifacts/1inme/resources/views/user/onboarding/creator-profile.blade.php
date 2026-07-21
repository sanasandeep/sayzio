@extends('user.layouts.app')
@section('title', 'Set up your creator profile')

@section('content')
<div class="max-w-xl mx-auto px-4 py-8 sm:py-12"
     x-data="{ stepIndex: {{ (int) ($activeIndex ?? 0) }}, moreOpen: false }">

    {{-- Visible progress indicator (shared with the onboarding wizard) --}}
    @includeWhen(!empty($steps), 'user.onboarding._stepper', ['steps' => $steps ?? []])

    <div class="glass rounded-3xl border border-white/10 overflow-hidden">
        {{-- Header --}}
        <div class="p-6 sm:p-8 bg-gradient-to-br from-blue-600/15 to-fuchsia-600/5 border-b border-white/10 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-blue-500/20 to-fuchsia-500/20 flex items-center justify-center mb-4">
                <i class="fas fa-id-badge text-blue-300 text-3xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white">Tell people about yourself</h1>
            <p class="text-sm text-white/60 mt-2 max-w-md mx-auto">
                A few quick details make your creator profile stand out: tagline, bio, location, and your niche.
                You can always refine everything later from Settings.
            </p>
        </div>

        <form method="POST" action="{{ route('user.onboarding.creator-profile.save') }}"
              enctype="multipart/form-data"
              class="p-6 sm:p-8 space-y-5">
            @csrf

            @if($errors->any())
                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            {{-- Primary fields (always visible) --}}
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-white/70 mb-1 block">Tagline</label>
                    <input type="text" name="tagline"
                           value="{{ old('tagline', $user->tagline) }}"
                           maxlength="200"
                           placeholder="e.g. Designer, cat collector &amp; occasional baker"
                           class="w-full px-3 py-2.5 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder:text-white/30 focus:outline-none focus:border-blue-500/60">
                </div>

                <div>
                    <label class="text-xs font-semibold text-white/70 mb-1 block">Bio</label>
                    <textarea name="bio" rows="3" maxlength="2000"
                              placeholder="Tell people who you are and what you create."
                              class="w-full px-3 py-2.5 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder:text-white/30 focus:outline-none focus:border-blue-500/60 resize-none">{{ old('bio', $user->bio) }}</textarea>
                </div>

                <div>
                    <label class="text-xs font-semibold text-white/70 mb-1 block">Location</label>
                    <input type="text" name="location"
                           value="{{ old('location', $user->location) }}"
                           maxlength="120"
                           placeholder="City, Country"
                           class="w-full px-3 py-2.5 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder:text-white/30 focus:outline-none focus:border-blue-500/60">
                </div>

                <div>
                    <label class="text-xs font-semibold text-white/70 mb-1 block">Niche tags <span class="font-normal text-white/40">(up to 8)</span></label>
                    <div class="flex flex-wrap gap-2 p-2.5 rounded-xl bg-white/5 border border-white/10 min-h-[42px]"
                         x-data="{
                             tags: @js($nicheTags),
                             input: '',
                             add() {
                                 const v = this.input.trim().toLowerCase();
                                 if (!v || this.tags.includes(v) || this.tags.length >= 8) { this.input = ''; return; }
                                 this.tags.push(v);
                                 this.input = '';
                             },
                             remove(t) { this.tags = this.tags.filter(x => x !== t); }
                         }">
                        <template x-for="t in tags" :key="t">
                            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-blue-500/20 text-blue-200 font-semibold">
                                #<span x-text="t"></span>
                                <button type="button" @click="remove(t)" class="text-blue-300/70 hover:text-rose-400 leading-none">&times;</button>
                                <input type="hidden" name="niche_tags[]" :value="t">
                            </span>
                        </template>
                        <input type="text" x-model="input"
                               @keydown.enter.prevent="add()"
                               @keydown.,.prevent="add()"
                               placeholder="type a tag, press Enter"
                               maxlength="32"
                               class="flex-1 min-w-[140px] bg-transparent text-xs text-white placeholder:text-white/30 outline-none py-1">
                    </div>
                    <p class="text-[11px] mt-1 text-white/35">Press Enter or comma after each tag. Examples: photography, travel, food.</p>
                </div>
            </div>

            {{-- "More options" expander --}}
            <div class="border-t border-white/10 pt-4">
                <button type="button"
                        @click="moreOpen = !moreOpen"
                        class="flex items-center gap-2 text-sm text-white/60 hover:text-white transition">
                    <i class="fas text-[10px] transition-transform" :class="moreOpen ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    <span x-text="moreOpen ? 'Fewer options' : 'More options'">More options</span>
                    <span class="text-white/35 text-xs font-normal">(cover image, socials, section visibility)</span>
                </button>

                <div x-show="moreOpen" x-cloak class="mt-4 space-y-5">
                    {{-- Cover image --}}
                    <div>
                        <label class="text-xs font-semibold text-white/70 mb-1 block">Cover image</label>
                        @if($user->cover_image)
                            <div class="mb-2 relative">
                                <img src="{{ \App\Support\PublicStorageUrl::resolve($user->cover_image) }}"
                                     class="w-full h-24 object-cover rounded-lg border border-white/10">
                                <label class="absolute top-2 right-2 text-[11px] px-2 py-0.5 rounded bg-white/90 text-slate-700 cursor-pointer">
                                    <input type="checkbox" name="cover_image_remove" value="1" class="mr-1"> Remove
                                </label>
                            </div>
                        @endif
                        <input type="file" name="cover_image" accept="image/*" class="text-xs text-white/60">
                        <p class="text-[11px] mt-1 text-white/35">Or paste a URL:</p>
                        <input type="url" name="cover_image_url"
                               placeholder="https://…" maxlength="1024"
                               class="w-full mt-1 px-3 py-2 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder:text-white/30 focus:outline-none focus:border-blue-500/60">
                    </div>

                    {{-- Socials --}}
                    <div>
                        <label class="text-xs font-semibold text-white/70 mb-2 block">Social links</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach($platforms as $key => $p)
                                <div>
                                    <label class="text-[11px] font-semibold mb-1 flex items-center gap-1 text-white/50">
                                        <i class="{{ $p['icon'] }} text-[11px]"></i> {{ $p['label'] }}
                                    </label>
                                    <input type="text"
                                           name="socials[{{ $key }}]"
                                           value="{{ old('socials.' . $key, $socials[$key] ?? '') }}"
                                           placeholder="{{ $p['placeholder'] }}"
                                           maxlength="200"
                                           class="w-full px-2.5 py-2 rounded-lg text-xs bg-white/5 border border-white/10 text-white placeholder:text-white/30 focus:outline-none focus:border-blue-500/60">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section visibility --}}
                    <div>
                        <label class="text-xs font-semibold text-white/70 mb-2 block">Sections shown on your profile</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach($sectionDefaults as $key => $default)
                                <label class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer bg-white/5 border border-white/10">
                                    <input type="hidden" name="sections[{{ $key }}]" value="0">
                                    <input type="checkbox"
                                           name="sections[{{ $key }}]"
                                           value="1"
                                           {{ ($sections[$key] ?? $default) ? 'checked' : '' }}
                                           class="rounded text-blue-500 border-white/20 bg-white/5">
                                    <span class="text-xs font-semibold capitalize text-white/70">{{ $key }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit"
                    class="w-full px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition">
                <i class="fas fa-check mr-1.5"></i> Save &amp; continue
            </button>
        </form>

        <p class="px-6 sm:px-8 pb-2 text-[11px] text-white/35 text-center -mt-2">
            Every field is optional; you can complete your profile anytime from
            <a href="{{ route('user.creator-profile.edit') }}" class="underline underline-offset-2 text-white/50 hover:text-white/80">Creator Profile settings</a>.
        </p>

        {{-- Skip --}}
        <div class="px-6 sm:px-8 py-4 border-t border-white/10 flex justify-center">
            <form method="POST" action="{{ route('user.onboarding.creator-profile.skip') }}">
                @csrf
                <button type="submit" class="text-sm text-white/50 hover:text-white/80 transition">
                    Skip for now <i class="fas fa-arrow-right text-xs ml-1"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
