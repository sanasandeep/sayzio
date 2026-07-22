@extends('admin.layouts.app')
@section('title', 'Social links')
@section('page-title', 'Social links')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 text-sm" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1 ak-strong">Social profile links</h2>
        <p class="text-sm text-white/50 mb-6 ak-muted">
            Paste the full URL to each of your brand's public social profiles. We'll show an icon for every network you fill in
            in the public site footer; networks left blank are hidden entirely. Each URL must start with <code class="text-blue-300 ak-blue">http://</code> or <code class="text-blue-300 ak-blue">https://</code>.
        </p>

        <form method="POST" action="{{ route('admin.social-links.update') }}" class="space-y-4">
            @csrf
            @foreach($networks as $key => $meta)
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5 ak-muted">
                        <i class="fa-brands {{ $meta['icon'] }} mr-1.5"></i>
                        {{ $meta['label'] }}
                    </label>
                    <input type="url"
                           name="{{ $key }}"
                           value="{{ old($key, $values[$key]) }}"
                           placeholder="{{ $meta['placeholder'] }}"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                    @error($key)
                        <p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="pt-3 border-t border-white/10">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">Save social links</button>
            </div>
        </form>
    </div>
</div>
@endsection
