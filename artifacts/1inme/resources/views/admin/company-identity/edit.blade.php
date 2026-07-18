@extends('admin.layouts.app')
@section('title', 'Company identity')
@section('page-title', 'Company identity')

@push('styles')
<style>
html.light-mode .ci-heading        { color: #0f172a; }
html.light-mode .ci-desc           { color: #64748b; }
html.light-mode .ci-info-box       { background: rgba(59,130,246,0.10); border-color: rgba(59,130,246,0.40); color: #1d4ed8; }
html.light-mode .ci-label          { color: #475569; }
html.light-mode .ci-input          { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
html.light-mode .ci-help           { color: #94a3b8; }
html.light-mode .ci-fallback       { color: #94a3b8; }
html.light-mode .ci-fallback-val   { color: #1d4ed8; }
html.light-mode .ci-divider        { border-color: #e2e8f0; }
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <h2 class="ci-heading text-lg font-semibold text-white mb-1">Legal company identity</h2>
        <p class="ci-desc text-sm text-white/50 mb-4">
            These details feed your legal pages (Terms, Privacy, Refunds, GDPR, Cookies), the public site footer and the
            marketing site. The governing-law jurisdiction is used in the Terms and dispute-resolution clauses. Leave a field
            blank to use its built-in default.
        </p>
        <div class="ci-info-box rounded-xl px-4 py-3 mb-6 text-sm bg-blue-500/[0.08] border border-blue-500/20 text-blue-300">
            Current effective jurisdiction: <strong>{{ $jurisdiction ?: '—' }}</strong>
        </div>

        <form method="POST" action="{{ route('admin.company-identity.update') }}" class="space-y-5">
            @csrf
            @foreach($fields as $key => $meta)
                <div>
                    <label for="{{ $key }}" class="ci-label block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">
                        {{ $meta['label'] }}
                    </label>
                    @if(($meta['type'] ?? 'text') === 'textarea')
                        <textarea id="{{ $key }}"
                                  name="{{ $key }}"
                                  rows="3"
                                  placeholder="{{ $defaults[$key] ?? '' }}"
                                  class="ci-input w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old($key, $values[$key]) }}</textarea>
                    @else
                        <input type="{{ ($meta['type'] ?? 'text') === 'email' ? 'email' : (($meta['type'] ?? 'text') === 'url' ? 'url' : 'text') }}"
                               id="{{ $key }}"
                               name="{{ $key }}"
                               value="{{ old($key, $values[$key]) }}"
                               placeholder="{{ $defaults[$key] ?? '' }}"
                               class="ci-input w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @endif
                    @if(!empty($meta['help']))
                        <p class="ci-help mt-1 text-xs text-white/40">{{ $meta['help'] }}</p>
                    @endif
                    @if(empty($values[$key]) && !empty($resolved[$key]))
                        <p class="ci-fallback mt-1 text-xs text-white/40">Currently using fallback: <span class="ci-fallback-val text-blue-300">{{ $resolved[$key] }}</span></p>
                    @endif
                    @error($key)
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="ci-divider pt-3 border-t border-white/10">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">Save company identity</button>
            </div>
        </form>
    </div>
</div>
@endsection
