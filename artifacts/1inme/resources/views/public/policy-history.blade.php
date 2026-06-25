@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-10 lg:pt-20 lg:pb-12 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:rgba(61,107,255,.06);"></div>
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="/{{ $page->slug }}" class="text-xs text-blue-400 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i>Back to {{ $page->title }}
        </a>
        <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight">Change history</h1>
        <p class="mt-2 text-sm text-gray-400">A record of every saved revision of <span class="text-gray-200">{{ $page->title }}</span>.</p>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($revisions->isEmpty())
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 text-center text-sm text-gray-400">
                No revisions have been recorded for this page yet.
            </div>
        @else
            <ol class="relative border-l border-white/10 pl-6 space-y-5">
                @foreach($revisions as $rev)
                    <li class="relative">
                        <span class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full bg-blue-500 ring-4 ring-blue-500/20"></span>
                        <div class="bg-white/[0.03] border border-white/10 rounded-xl p-4">
                            <div class="flex items-baseline justify-between gap-3 flex-wrap">
                                <p class="text-sm font-semibold text-white">
                                    {{ $rev->created_at->format('F j, Y') }}
                                    <span class="text-xs font-normal text-gray-400 ml-1">{{ $rev->created_at->format('g:i a') }}</span>
                                </p>
                                <span class="text-[11px] text-gray-500">Revision #{{ $rev->id }}</span>
                            </div>
                            @if($rev->summary)
                                <p class="mt-1.5 text-sm text-gray-300">{{ $rev->summary }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
@endsection
