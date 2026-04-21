@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        @foreach(($page->sections ?? []) as $section)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
                @if(!empty($section['heading']))
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">{{ $section['heading'] }}</h2>
                @endif
                <div class="prose-light text-gray-300 leading-relaxed">
                    {!! \App\Services\SafeHtml::render($section['body'] ?? '') !!}
                </div>
            </div>
        @endforeach
        @if(empty($page->sections))
            <div class="text-center text-gray-500 text-sm">This page hasn't been written yet.</div>
        @endif
    </div>
</section>
@endsection
