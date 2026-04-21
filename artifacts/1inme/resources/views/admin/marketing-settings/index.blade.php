@extends('admin.layouts.app')
@section('title', 'Marketing Settings')
@section('content')
@php
    $defaultsTrust = \App\Modules\Common\Support\SitePagesContent::trustStripDefault();
    $defaultsTest = \App\Modules\Common\Support\SitePagesContent::testimonialsDefault();
    $trustForJs = !empty($trust_strip) ? $trust_strip : $defaultsTrust;
    $landingForJs = !empty($landing_testimonials) ? $landing_testimonials : $defaultsTest;
    $featuresForJs = !empty($features_testimonials) ? $features_testimonials : $defaultsTest;
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

        {{-- Trust strip --}}
        <div class="glass rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Hero trust strip</h2>
                    <p class="text-xs text-white/50">Shown under the landing-page hero. Up to 6 items.</p>
                </div>
                <button type="button" @click="if(trust.length<6) trust.push({value:'',label:'',icon:'fa-circle-check'})"
                        class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                    <i class="fas fa-plus mr-1"></i> Add metric
                </button>
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
