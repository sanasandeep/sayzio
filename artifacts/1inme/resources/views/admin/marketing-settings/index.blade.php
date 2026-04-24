@extends('admin.layouts.app')
@section('title', 'Marketing Settings')
@section('content')
@php
    $defaultsTrust = \App\Modules\Common\Support\SitePagesContent::trustStripDefault();
    $defaultsTest = \App\Modules\Common\Support\SitePagesContent::testimonialsDefault();
    $defaultsWhy = \App\Modules\Common\Support\SitePagesContent::whyComparisonDefault();
    $trustForJs = !empty($trust_strip) ? $trust_strip : $defaultsTrust;
    $landingForJs = !empty($landing_testimonials) ? $landing_testimonials : $defaultsTest;
    $featuresForJs = !empty($features_testimonials) ? $features_testimonials : $defaultsTest;
    $whyForJs = !empty($why_comparison) ? $why_comparison : $defaultsWhy;
@endphp
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-violet-400 hover:underline">
        <i class="fas fa-arrow-left mr-1"></i>Back to all pages
    </a>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing-settings.update') }}"
          x-data='{
              trust: @json($trustForJs),
              landing: @json($landingForJs),
              features: @json($featuresForJs),
              why: @json($whyForJs),
              trustDefaults: @json($defaultsTrust),
              testDefaults: @json($defaultsTest),
              whyDefaults: @json($defaultsWhy),
              resetTo(key, defaults) {
                  var self = this;
                  window.themedConfirm({
                      title: 'Reset this section?',
                      message: 'Your current rows will be replaced with the shipped defaults. You still need to click Save to keep the change.',
                      confirmText: 'Reset',
                      confirmIcon: 'fa-rotate-left',
                      iconClass: 'fa-rotate-left',
                      onConfirm: function () {
                          self[key] = JSON.parse(JSON.stringify(defaults));
                      },
                  });
              },
          }'
          class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Tracking & share image --}}
        <div class="glass rounded-2xl p-6 space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-white">Tracking &amp; share preview</h2>
                <p class="text-xs text-white/50">Injected only on marketing pages (landing, features, how-it-works, faqs, about, contact, services, policies). Dashboard, biolinks and short-link redirects are never touched.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Google Analytics 4 — Measurement ID</label>
                    <input type="text" name="ga4_id" value="{{ old('ga4_id', $ga4_id) }}" placeholder="G-XXXXXXX"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('ga4_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Meta (Facebook) Pixel ID</label>
                    <input type="text" name="meta_pixel_id" value="{{ old('meta_pixel_id', $meta_pixel_id) }}" placeholder="1234567890"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('meta_pixel_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Default share preview image (URL)</label>
                <input type="url" name="default_share_image" value="{{ old('default_share_image', $default_share_image) }}"
                       placeholder="https://yourdomain.com/og-image.png"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <p class="mt-1 text-[11px] text-white/40">Used as the default Open Graph / Twitter Card image when a page does not specify its own. 1200×630 PNG/JPG recommended.</p>
                @error('default_share_image')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Subscribe block — WhatsApp settings --}}
        <div class="glass rounded-2xl p-6 space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-white">3-way Subscribe block — WhatsApp</h2>
                <p class="text-xs text-white/50">Powers the WhatsApp Channel and WhatsApp DM cards in the public Subscribe block (every marketing page) and the compact version in the site footer. Leave a field blank to hide that card.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">WhatsApp Channel URL</label>
                <input type="url" name="whatsapp_channel_url" value="{{ old('whatsapp_channel_url', $whatsapp_channel_url) }}"
                       placeholder="https://whatsapp.com/channel/0029Vb..."
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <p class="mt-1 text-[11px] text-white/40">Public invite link to the brand's WhatsApp Channel. When empty, the "WhatsApp Channel" card is hidden across the site.</p>
                @error('whatsapp_channel_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">WhatsApp DM number (E.164)</label>
                    <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $whatsapp_number) }}"
                           placeholder="+15551234567"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    <p class="mt-1 text-[11px] text-white/40">Country code + number, no spaces. Used to build the wa.me link for the "Chat on WhatsApp" card.</p>
                    @error('whatsapp_number')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Default opening message</label>
                    <input type="text" name="whatsapp_message" value="{{ old('whatsapp_message', $whatsapp_message) }}"
                           placeholder="Hi 1INME! I'd like to learn more."
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <p class="mt-1 text-[11px] text-white/40">Pre-fills the WhatsApp chat. Optional.</p>
                    @error('whatsapp_message')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Trust strip --}}
        <div class="glass rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Hero trust strip</h2>
                    <p class="text-xs text-white/50">Shown under the landing-page hero. Up to 6 items.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetTo('trust', trustDefaults)"
                            class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-white/80">
                        <i class="fas fa-rotate-left mr-1"></i> Reset to defaults
                    </button>
                    <button type="button" @click="if(trust.length<6) trust.push({value:'',label:'',icon:'fa-circle-check'})"
                            class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                        <i class="fas fa-plus mr-1"></i> Add metric
                    </button>
                </div>
            </div>
            <template x-for="(t,i) in trust" :key="i">
                <div class="bg-white/5 border border-white/10 rounded-xl p-3 grid sm:grid-cols-[1fr_2fr_1fr_auto] gap-2 items-center">
                    <input type="text" :name="'trust_strip['+i+'][value]'" x-model="t.value" placeholder="12,000+"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'trust_strip['+i+'][label]'" x-model="t.label" placeholder="Active creators"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'trust_strip['+i+'][icon]'" x-model="t.icon" placeholder="fa-users"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    <button type="button" @click="trust.splice(i,1)" class="text-red-400 hover:text-red-300 text-xs px-2"><i class="fas fa-trash"></i></button>
                </div>
            </template>
            <p x-show="trust.length===0" class="text-xs text-white/40">No metrics yet — add at least one to show the strip.</p>

            {{-- Live preview --}}
            <div x-show="trust.length>0" class="mt-2 pt-4 border-t border-white/5">
                <div class="text-[10px] uppercase tracking-wider text-white/40 mb-3">Live preview</div>
                <div class="rounded-xl bg-gradient-to-br from-slate-900 to-slate-950 border border-white/10 px-4 py-4">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                        <template x-for="(t,i) in trust" :key="'tp'+i">
                            <span class="flex items-center gap-2 text-gray-400">
                                <i class="fas text-[13px] text-violet-300"
                                   :class="(t.icon || 'fa-check').replace(/^fas?\s+/, '')"></i>
                                <span class="font-bold text-white" x-text="t.value || '—'"></span>
                                <span class="text-gray-500" x-text="t.label || ''"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Why 1INME comparison --}}
        <div class="glass rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Why 1INME comparison</h2>
                    <p class="text-xs text-white/50">Rows in the comparison table on the landing page (just before pricing). Up to 12 rows. If the "1INME" column is left as <span class="font-mono">Yes</span> it renders as the green check pill; any other text is shown verbatim.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetTo('why', whyDefaults)"
                            class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-white/80">
                        <i class="fas fa-rotate-left mr-1"></i> Reset to defaults
                    </button>
                    <button type="button" @click="if(why.length<12) why.push({feature:'',ours:'Yes',theirs:''})"
                            class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                        <i class="fas fa-plus mr-1"></i> Add row
                    </button>
                </div>
            </div>
            <div class="hidden sm:grid grid-cols-[2fr_1fr_1fr_auto] gap-2 px-1 text-[11px] font-bold uppercase tracking-wider text-white/40">
                <div>Feature</div>
                <div>1INME</div>
                <div>Competitor</div>
                <div></div>
            </div>
            <template x-for="(w,i) in why" :key="i">
                <div class="bg-white/5 border border-white/10 rounded-xl p-3 grid sm:grid-cols-[2fr_1fr_1fr_auto] gap-2 items-center">
                    <input type="text" :name="'why_comparison['+i+'][feature]'" x-model="w.feature" placeholder="Drag-and-drop biolink page"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'why_comparison['+i+'][ours]'" x-model="w.ours" placeholder="Yes"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'why_comparison['+i+'][theirs]'" x-model="w.theirs" placeholder="Limited"
                           class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <button type="button" @click="why.splice(i,1)" class="text-red-400 hover:text-red-300 text-xs px-2"><i class="fas fa-trash"></i></button>
                </div>
            </template>
            <p x-show="why.length===0" class="text-xs text-white/40">No rows yet — the comparison section will be hidden on the landing page.</p>

            {{-- Live preview --}}
            <div x-show="why.length>0" class="mt-2 pt-4 border-t border-white/5">
                <div class="text-[10px] uppercase tracking-wider text-white/40 mb-3">Live preview</div>
                <div class="rounded-2xl overflow-hidden border border-white/10 bg-gradient-to-br from-slate-900 to-slate-950">
                    <div class="grid grid-cols-12 px-4 py-3 bg-white/[.04] text-[11px] font-bold uppercase tracking-wider text-gray-400">
                        <div class="col-span-6">Feature</div>
                        <div class="col-span-3 text-center text-white">1INME</div>
                        <div class="col-span-3 text-center">Typical bio-link tool</div>
                    </div>
                    <template x-for="(r,i) in why" :key="'wp'+i">
                        <div class="grid grid-cols-12 items-center px-4 py-3 border-t border-white/5 text-sm">
                            <div class="col-span-6 text-gray-200" x-text="r.feature || '—'"></div>
                            <div class="col-span-3 text-center">
                                <template x-if="(r.ours || '').trim().toLowerCase() === 'yes'">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-300 text-xs font-semibold"><i class="fas fa-check"></i> Yes</span>
                                </template>
                                <template x-if="(r.ours || '').trim().toLowerCase() !== 'yes'">
                                    <span class="text-white text-xs font-semibold" x-text="r.ours || ''"></span>
                                </template>
                            </div>
                            <div class="col-span-3 text-center text-gray-400 text-xs" x-text="r.theirs || ''"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Landing testimonials --}}
        @include('admin.marketing-settings.partials._testimonial-editor', [
            'fieldName' => 'landing_testimonials',
            'modelKey'  => 'landing',
            'title'     => 'Landing-page testimonials',
            'helper'    => 'Shown in the carousel below the landing hero. Empty list hides the section.',
        ])

        {{-- Features testimonials --}}
        @include('admin.marketing-settings.partials._testimonial-editor', [
            'fieldName' => 'features_testimonials',
            'modelKey'  => 'features',
            'title'     => 'Features-page testimonials',
            'helper'    => 'Shown near the end of /features. Empty list hides the section.',
        ])

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save settings</button>
        </div>
    </form>
</div>
@endsection
