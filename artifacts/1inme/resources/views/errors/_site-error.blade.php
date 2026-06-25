@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(61,107,255,0.18) 0%,transparent 70%);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="text-xs uppercase tracking-[0.3em] text-blue-400 mb-3">Error {{ $statusCode ?? '' }}</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @foreach(($page->sections ?? []) as $section)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
                @if(!empty($section['heading']))
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">{{ $section['heading'] }}</h2>
                @endif
                <div class="prose-light text-gray-300 leading-relaxed">
                    {!! nl2br(e($section['body'] ?? '')) !!}
                </div>
            </div>
        @endforeach

        @if(!empty($suggestions ?? []))
            <div class="bg-blue-500/[0.06] border border-blue-400/20 rounded-2xl p-6 sm:p-8">
                <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">Did you mean…?</h2>
                <p class="text-sm text-gray-400 mb-4">These existing pages look close to the URL you typed:</p>
                <ul class="space-y-2">
                    @foreach($suggestions as $s)
                        @php
                            $kindLabel = match($s['kind'] ?? '') {
                                'biolink'    => 'Link in Bio',
                                'short_link' => 'Short link',
                                'page'       => 'Site page',
                                default      => 'Page',
                            };
                        @endphp
                        <li>
                            <a href="{{ $s['url'] }}" class="flex items-center justify-between gap-4 px-4 py-3 bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 rounded-xl text-sm text-white transition">
                                <span class="font-mono truncate">{{ $s['label'] }}</span>
                                <span class="text-[10px] uppercase tracking-wider text-blue-300 shrink-0">{{ $kindLabel }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($page->cta_label) && !empty($page->cta_url))
            <div class="text-center pt-2">
                <a href="{{ $page->cta_url }}" class="inline-flex items-center px-6 py-3 bg-[#3d6bff] hover:bg-[#2342c7] text-white rounded-full text-sm font-bold">
                    {{ $page->cta_label }}
                </a>
            </div>
        @endif
    </div>
</section>
@endsection
