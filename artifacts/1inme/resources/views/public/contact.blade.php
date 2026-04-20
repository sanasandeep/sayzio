@extends('public.layouts.site')
@section('content')
<section class="pt-16 pb-8 lg:pt-24 text-center">
    <div class="max-w-3xl mx-auto px-4">
        <h1 class="text-4xl sm:text-5xl font-bold">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>
<section class="pb-20">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @foreach(($page->sections ?? []) as $section)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 mb-6">
                @if(!empty($section['heading']))<h2 class="text-lg font-bold mb-2">{{ $section['heading'] }}</h2>@endif
                <div class="text-gray-300 text-sm">{!! nl2br(e($section['body'] ?? '')) !!}</div>
            </div>
        @endforeach

        @if(session('success'))
            <div class="rounded-xl px-4 py-3 text-sm mb-4" style="background:rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.30); color:#86efac;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-xl px-4 py-3 text-sm mb-4 bg-red-500/10 border border-red-500/30 text-red-300">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.contact.submit') }}" class="space-y-4 bg-white/[0.03] border border-white/10 rounded-2xl p-6">
            @csrf
            {{-- honeypot --}}
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Your name</label>
                    <input type="text" name="name" required value="{{ old('name') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                    <input type="email" name="email" required value="{{ old('email') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Subject</label>
                <input type="text" name="subject" required value="{{ old('subject') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Message</label>
                <textarea name="message" required rows="6" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-bold text-white">
                Send message
            </button>
        </form>
    </div>
</section>
@endsection
