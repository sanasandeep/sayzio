@extends('public.layouts.site')
@section('content')
@php
    $sections = $page->visibleSections();
    $showToc = ($page->show_toc ?? true) && count($sections) > 0;
    $lastUpdated = $page->last_updated_at;
@endphp
<section class="relative pt-16 pb-10 lg:pt-24 lg:pb-12 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $page->title }}</h1>
        @if($lastUpdated || ($hasHistory ?? false))
            <p class="mt-3 text-sm text-gray-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                @if($lastUpdated)
                    <span>
                        <i class="far fa-calendar-alt mr-1.5"></i>
                        Last updated: <span class="text-gray-300">{{ \Illuminate\Support\Carbon::parse($lastUpdated)->format('F j, Y') }}</span>
                    </span>
                @endif
                @if($hasHistory ?? false)
                    <a href="{{ route('site.policy.history', $page->slug) }}" class="text-violet-400 hover:text-violet-300 underline-offset-2 hover:underline">
                        <i class="fas fa-clock-rotate-left mr-1"></i>View change history
                    </a>
                @endif
            </p>
        @endif
        @if($page->intro)
            <p class="mt-5 text-lg text-gray-300 leading-relaxed max-w-3xl">{{ $page->intro }}</p>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-10">
            @if($showToc)
                <aside class="lg:col-span-4 mb-8 lg:mb-0">
                    <nav class="lg:sticky lg:top-24 bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/60 mb-3">On this page</p>
                        <ol class="space-y-2 text-sm">
                            @foreach($sections as $s)
                                @php $id = $s['id'] ?? \Illuminate\Support\Str::slug($s['heading'] ?? ''); @endphp
                                <li>
                                    <a href="#{{ $id }}" class="text-gray-300 hover:text-white block leading-snug">
                                        {{ $s['heading'] ?? '' }}
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </aside>
            @endif

            <div class="{{ $showToc ? 'lg:col-span-8' : 'lg:col-span-12' }} space-y-8">
                @foreach($sections as $s)
                    @php $id = $s['id'] ?? \Illuminate\Support\Str::slug($s['heading'] ?? ''); @endphp
                    <article id="{{ $id }}" class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 scroll-mt-24">
                        @if(!empty($s['heading']))
                            <h2 class="text-xl sm:text-2xl font-bold mb-4 text-white">
                                <a href="#{{ $id }}" class="group inline-flex items-center gap-2">
                                    <span>{{ $s['heading'] }}</span>
                                    <i class="fas fa-link text-xs text-white/20 group-hover:text-white/60 transition-colors"></i>
                                </a>
                            </h2>
                        @endif
                        <div class="prose-light text-gray-300 leading-relaxed space-y-3">
                            {!! \App\Services\SafeHtml::render($s['body'] ?? '') !!}
                        </div>
                    </article>
                @endforeach

                @if(empty($sections))
                    <div class="text-center text-gray-500 text-sm py-12">This page hasn't been written yet.</div>
                @endif

                @php
                    $contactEmail = \App\Modules\Admin\Models\AppSetting::get('contact_recipient_email');
                @endphp
                <div class="bg-gradient-to-br from-violet-500/10 to-fuchsia-500/10 border border-violet-500/20 rounded-2xl p-6 sm:p-8">
                    <h3 class="text-lg font-semibold text-white mb-2">Questions about this policy?</h3>
                    <p class="text-sm text-gray-300 mb-4">
                        We're happy to clarify anything you read on this page. Reach out and a real person on our team will get back to you.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('site.contact') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-medium">
                            <i class="far fa-envelope"></i> Contact us
                        </a>
                        @if($contactEmail)
                            <a href="mailto:{{ $contactEmail }}" class="text-sm text-gray-300 hover:text-white">
                                <i class="far fa-envelope-open mr-1"></i> {{ $contactEmail }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
