@extends('public.layouts.site')

@push('head')
@php
    $__faqsForSchema = collect($faqs ?? [])
        ->filter(fn ($f) => trim((string) $f->question) !== '' && trim((string) $f->answer) !== '')
        ->map(fn ($f) => [
            '@type' => 'Question',
            'name' => (string) $f->question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => (string) $f->answer,
            ],
        ])
        ->values();
@endphp
@if($__faqsForSchema->isNotEmpty())
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => $__faqsForSchema,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endif
@endpush

@section('content')
<section class="pt-16 pb-12 lg:pt-24 lg:pb-16 text-center">
    <div class="max-w-3xl mx-auto px-4">
        <h1 class="text-4xl sm:text-5xl font-bold">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>
<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3" x-data="{ open: null }">
        @forelse($faqs as $i => $f)
            <div class="bg-white/[0.03] border border-white/10 rounded-xl">
                <button type="button" @click="open = open==={{ $i }} ? null : {{ $i }}"
                        class="w-full flex justify-between items-center text-left px-5 py-4">
                    <span class="font-semibold text-white">{{ $f->question }}</span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform"
                       :class="open==={{ $i }} ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open==={{ $i }}" x-cloak class="px-5 pb-4 text-gray-300 text-sm leading-relaxed">
                    {!! nl2br(e($f->answer)) !!}
                </div>
            </div>
        @empty
            <div class="text-center text-gray-500">No FAQs yet.</div>
        @endforelse
    </div>
</section>
@endsection
