@extends('user.layouts.app')
@section('title', 'Referrals')

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canEdit = $__can('referrals.edit');
@endphp
<div class="max-w-4xl">
    <h1 class="text-2xl font-bold text-white mb-1">Refer friends, earn free days</h1>
    <p class="text-sm text-white/50 mb-6">Share your link or code. When a friend signs up and activates a paid plan, you both get free subscription days.</p>

    @if(!$enabled)
        <div class="rounded-xl px-4 py-3 mb-6 bg-yellow-500/10 border border-yellow-500/30 text-yellow-200 text-sm">
            The referral program is currently disabled by the administrator.
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Code + URL --}}
    <div class="grid md:grid-cols-2 gap-4 mb-6">
        <div class="glass rounded-2xl p-5">
            <div class="text-xs uppercase tracking-wider text-white/40 mb-2">Your referral code</div>
            <div class="flex gap-2 items-center">
                <code class="flex-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white font-mono text-sm" id="ref-code-display">{{ $user->referral_code }}</code>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('ref-code-display').innerText); this.innerText='Copied'" class="px-3 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-medium hover:bg-blue-700">Copy</button>
            </div>
        </div>
        <div class="glass rounded-2xl p-5">
            <div class="text-xs uppercase tracking-wider text-white/40 mb-2">Your referral link</div>
            <div class="flex gap-2 items-center">
                <input type="text" readonly value="{{ $referralUrl }}" class="flex-1 px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white text-sm" id="ref-url-display">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('ref-url-display').value); this.innerText='Copied'" class="px-3 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-medium hover:bg-blue-700">Copy</button>
            </div>
        </div>
    </div>

    {{-- Edit code --}}
    @if($__canEdit)
    <div class="glass rounded-2xl p-5 mb-6">
        <h2 class="text-sm font-semibold text-white/80 mb-3">Customize your code</h2>
        <form method="POST" action="{{ route('user.referrals.code.update') }}" class="flex flex-col sm:flex-row gap-2 items-start" id="ref-code-form">
            @csrf @method('PUT')
            <div class="flex-1 w-full">
                <input type="text" name="code" id="ref-code-input" value="{{ old('code', $user->referral_code) }}" minlength="3" maxlength="32" required
                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white font-mono text-sm focus:ring-2 focus:ring-blue-500/40 outline-none">
                <p class="mt-1 text-xs" id="ref-code-feedback" style="color: var(--text-faint);">3–32 characters · letters, numbers, dashes or underscores</p>
                @error('code')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Save code</button>
        </form>
    </div>
    @else
    <div class="rounded-2xl p-4 mb-6 flex items-start gap-3" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); color:#b45309;">
        <i class="fas fa-lock mt-0.5"></i>
        <div class="text-xs">
            <div class="font-semibold mb-0.5">Customizing your code is view-only</div>
            <span style="color: var(--text-muted);">Your role doesn't allow changing the workspace referral code. Ask a workspace admin to update it for you.</span>
        </div>
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach([
            ['Clicks', $stats['clicks'], 'fa-mouse-pointer'],
            ['Signups', $stats['signups'], 'fa-user-plus'],
            ['Conversions', $stats['conversions'], 'fa-check-circle'],
            ['Free days earned', $stats['days_earned'], 'fa-gift'],
        ] as [$label, $val, $icon])
            <div class="glass rounded-xl p-4">
                <div class="text-[11px] uppercase tracking-wider text-white/40 flex items-center gap-2"><i class="fas {{ $icon }}"></i> {{ $label }}</div>
                <div class="text-2xl font-bold text-white mt-1">{{ number_format($val) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Referred users list --}}
    <div class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10 text-sm font-semibold text-white/80">People you've referred</div>
        @if($referrals->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-white/40">No referrals yet. Share your link to get started.</div>
        @else
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02]">
                <tr><th class="px-5 py-2 text-left">Person</th><th class="px-5 py-2 text-left">Status</th><th class="px-5 py-2 text-left">Signed up</th><th class="px-5 py-2 text-left">Converted</th></tr>
            </thead>
            <tbody>
            @foreach($referrals as $r)
                <tr class="border-t border-white/5">
                    <td class="px-5 py-3 text-white/80">{{ $r->referredUser?->name ?? '—' }} <span class="text-white/30">{{ $r->referredUser?->email }}</span></td>
                    <td class="px-5 py-3">
                        @php
                            $color = ['rewarded' => 'emerald', 'converted' => 'sky', 'signed_up' => 'violet'][$r->status] ?? 'slate';
                            $label = ['rewarded' => 'Rewarded', 'converted' => 'Converted', 'signed_up' => 'Signed up'][$r->status] ?? ucfirst($r->status);
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-{{ $color }}-500/10 text-{{ $color }}-300 border border-{{ $color }}-500/30">{{ $label }}</span>
                    </td>
                    <td class="px-5 py-3 text-white/60">{{ $r->signed_up_at?->diffForHumans() }}</td>
                    <td class="px-5 py-3 text-white/60">{{ $r->converted_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="px-5 py-3">{{ $referrals->links() }}</div>
        @endif
    </div>
</div>

<script>
(function() {
    const input = document.getElementById('ref-code-input');
    const feedback = document.getElementById('ref-code-feedback');
    if (!input) return;
    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const v = input.value.trim();
            if (!v) return;
            try {
                const r = await fetch('{{ route('user.referrals.check') }}?code=' + encodeURIComponent(v));
                const j = await r.json();
                feedback.textContent = j.message;
                feedback.style.color = j.ok ? '#34d399' : '#f87171';
            } catch (_) {}
        }, 300);
    });
})();
</script>
@endsection
