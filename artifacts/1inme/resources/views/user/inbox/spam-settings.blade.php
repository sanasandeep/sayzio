@extends('user.layouts.app')
@section('title', 'Spam Settings')

@section('content')
@php
    $defaultDisabled = $spam['disabled_default_keywords'] ?? [];
    $blockedText = implode("\n", $spam['blocked_keywords'] ?? []);
    $emailsText  = implode("\n", $spam['trusted_emails'] ?? []);
    $phonesText  = implode("\n", $spam['trusted_phones'] ?? []);
@endphp
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('user.inbox.index') }}" class="p-2 rounded-xl glass transition hover:bg-white/5" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Spam Settings</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-muted);">Tune the keywords and senders the spam filter uses for your inbox.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-6 p-3 rounded-xl text-sm font-medium" style="background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2);">
        <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 p-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-exclamation-circle mr-1.5"></i>
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.inbox.spam-settings.update') }}">
        @csrf

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(234,88,12,0.15);">
                    <i class="fas fa-ban text-orange-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Default keyword list</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Uncheck any default keyword that produces false positives in your niche.</p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($defaults as $kw)
                    @php $isDisabled = in_array(mb_strtolower($kw), array_map('mb_strtolower', $defaultDisabled), true); @endphp
                    <label class="flex items-center gap-2 text-xs px-3 py-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                        <input type="checkbox" name="disabled_default_keywords[]" value="{{ $kw }}" {{ $isDisabled ? 'checked' : '' }}>
                        <span class="{{ $isDisabled ? 'line-through opacity-60' : '' }}">{{ $kw }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-3 text-[11px]" style="color: var(--text-faint);">Checked = disabled. Unchecked = active.</p>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.2);">
                    <i class="fas fa-plus text-violet-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Your custom blocked keywords</h2>
                    <p class="text-xs" style="color: var(--text-muted);">One per line (or comma-separated). Case-insensitive substring match.</p>
                </div>
            </div>
            <textarea name="blocked_keywords" rows="6" placeholder="example phrase&#10;another spammy term"
                class="w-full px-3 py-2.5 rounded-xl text-sm outline-none resize-y font-mono"
                style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">{{ old('blocked_keywords', $blockedText) }}</textarea>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(34,197,94,0.15);">
                    <i class="fas fa-shield-alt text-emerald-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Trusted senders</h2>
                    <p class="text-xs" style="color: var(--text-muted);">Submissions from these emails or phones always pass the spam filter.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Trusted emails</label>
                    <textarea name="trusted_emails" rows="6" placeholder="vip@example.com&#10;client@partner.io"
                        class="w-full px-3 py-2.5 rounded-xl text-sm outline-none resize-y font-mono"
                        style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">{{ old('trusted_emails', $emailsText) }}</textarea>
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Trusted phones</label>
                    <textarea name="trusted_phones" rows="6" placeholder="+15551234567&#10;+442071234567"
                        class="w-full px-3 py-2.5 rounded-xl text-sm outline-none resize-y font-mono"
                        style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">{{ old('trusted_phones', $phonesText) }}</textarea>
                </div>
            </div>
            <p class="mt-3 text-[11px]" style="color: var(--text-faint);">Tip: in the inbox, use “Not spam &amp; trust sender” to add a sender here automatically.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                <i class="fas fa-save mr-1.5"></i>Save Spam Settings
            </button>
        </div>
    </form>
</div>
@endsection
