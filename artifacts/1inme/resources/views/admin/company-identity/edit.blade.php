@extends('admin.layouts.app')
@section('title', 'Company identity')
@section('page-title', 'Company identity')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 text-sm" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Legal company identity</h2>
        <p class="text-sm text-white/50 mb-4">
            These details feed your legal pages (Terms, Privacy, Refunds, GDPR, Cookies), the public site footer and the
            marketing site. The governing-law jurisdiction is used in the Terms and dispute-resolution clauses. Leave a field
            blank to use its built-in default.
        </p>
        <div class="rounded-xl px-4 py-3 mb-6 text-sm" style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.20); color: #93c5fd;">
            Current effective jurisdiction: <strong>{{ $jurisdiction ?: '—' }}</strong>
        </div>

        <form method="POST" action="{{ route('admin.company-identity.update') }}" class="space-y-5">
            @csrf
            @foreach($fields as $key => $meta)
                <div>
                    <label for="{{ $key }}" class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">
                        {{ $meta['label'] }}
                    </label>
                    @if(($meta['type'] ?? 'text') === 'textarea')
                        <textarea id="{{ $key }}"
                                  name="{{ $key }}"
                                  rows="3"
                                  placeholder="{{ $defaults[$key] ?? '' }}"
                                  class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old($key, $values[$key]) }}</textarea>
                    @else
                        <input type="{{ ($meta['type'] ?? 'text') === 'email' ? 'email' : (($meta['type'] ?? 'text') === 'url' ? 'url' : 'text') }}"
                               id="{{ $key }}"
                               name="{{ $key }}"
                               value="{{ old($key, $values[$key]) }}"
                               placeholder="{{ $defaults[$key] ?? '' }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @endif
                    @if(!empty($meta['help']))
                        <p class="mt-1 text-xs text-white/40">{{ $meta['help'] }}</p>
                    @endif
                    @if(empty($values[$key]) && !empty($resolved[$key]))
                        <p class="mt-1 text-xs text-white/40">Currently using fallback: <span class="text-blue-300">{{ $resolved[$key] }}</span></p>
                    @endif
                    @error($key)
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="pt-3 border-t border-white/10">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">Save company identity</button>
            </div>
        </form>
    </div>
</div>
@endsection
