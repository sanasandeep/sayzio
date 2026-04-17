@extends('user.layouts.app')
@section('title', 'New Social Proof')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('user.social-proofs.index') }}" class="text-white/50 hover:text-white text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <h1 class="text-2xl font-bold text-white mt-2">Choose a notification type</h1>
        <p class="text-white/40 text-sm mt-1">Pick the kind of social proof you want to display.</p>
    </div>

    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
        @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('user.social-proofs.store') }}" class="space-y-6">
        @csrf
        <div class="glass rounded-2xl p-5">
            <label class="block text-white/70 text-sm mb-2">Name</label>
            <input type="text" name="name" required value="{{ old('name', 'My notifications') }}"
                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white placeholder-white/30 focus:outline-none focus:border-violet-500">
        </div>

        @php
            $icons = [
                'recent_activity' => ['fa-bolt',         'Show real or curated recent activity (signups, purchases, downloads).'],
                'visitor_count'   => ['fa-eye',          'Live counter of people on the page right now.'],
                'conversion_count'=> ['fa-chart-line',   'Total number of recent purchases / signups in a window.'],
                'email_signup'    => ['fa-envelope',     'Inline email capture prompt.'],
                'countdown'       => ['fa-hourglass-half','Countdown timer to a fixed date / time.'],
                'review'          => ['fa-star',         'Rotating customer reviews with star rating.'],
                'custom_html'     => ['fa-code',         'Render any custom HTML you like (sanitized).'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($types as $key => $label)
            <label class="cursor-pointer block">
                <input type="radio" name="type" value="{{ $key }}" class="peer hidden" {{ $loop->first ? 'checked' : '' }}>
                <div class="glass rounded-2xl p-4 border-2 border-transparent peer-checked:border-violet-500 hover:bg-white/[0.06] transition flex gap-4">
                    <div class="w-11 h-11 rounded-xl bg-violet-500/15 text-violet-300 flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $icons[$key][0] ?? 'fa-bell' }}"></i>
                    </div>
                    <div>
                        <div class="text-white font-semibold">{{ $label }}</div>
                        <div class="text-white/40 text-xs mt-1">{{ $icons[$key][1] ?? '' }}</div>
                    </div>
                </div>
            </label>
            @endforeach
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.social-proofs.index') }}" class="px-4 py-2 rounded-xl text-white/70 hover:bg-white/5 text-sm">Cancel</a>
            <button class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-xl text-sm font-medium">Continue</button>
        </div>
    </form>
</div>
@endsection
