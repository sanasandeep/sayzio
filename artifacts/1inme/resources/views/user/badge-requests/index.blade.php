@extends('user.layouts.settings')
@section('title', 'Request a badge')
@section('settings-content')
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Account badges</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">Request a badge for your account. Our team reviews every request.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg text-emerald-400 text-sm" style="border:1px solid rgba(16,185,129,0.2); background: rgba(16,185,129,0.06);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg text-red-400 text-sm" style="border:1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">{{ session('error') }}</div>
    @endif

    @php $mine = auth()->user()->accountBadges; @endphp
    @if($mine->isNotEmpty())
    <div class="mb-6 rounded-2xl border p-4" style="background: var(--bg-card); border-color: var(--border-soft);">
        <h2 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-muted);">Your badges</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($mine as $b)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold text-white" style="background: {{ $b->color }};">
                    <i class="fas fa-certificate text-[10px]"></i> {{ $b->name }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Give a badge (Task #3045): a creator passes on a badge they hold to
         another account, found by handle. Only badges the creator currently
         holds are offered; ownership is re-verified server-side on submit. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start mb-8">
    <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft);">
        <h2 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Give a badge</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Pass one of your badges to another account by their handle. They'll be notified, and your name is recorded as the giver.</p>

        @if($mine->isEmpty())
            <div class="text-sm rounded-lg p-3" style="background: var(--bg-subtle); color: var(--text-muted); border:1px solid var(--border-soft);">
                <i class="fas fa-circle-info mr-1.5"></i> You don't hold any badges yet, so there's nothing to give. Once you earn a badge it'll appear here to share.
            </div>
        @else
        <form method="POST" action="{{ route('user.badge-requests.give') }}"
              x-data="giveBadge('{{ route('user.badge-requests.give.lookup') }}')" @submit="onSubmit($event)">
            @csrf
            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Recipient handle</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color: var(--text-faint);">@</span>
                    <input type="text" name="handle" x-model="handle" @input.debounce.400ms="lookup()" autocomplete="off"
                           value="{{ old('handle') }}" maxlength="120" placeholder="theirhandle"
                           class="w-full pl-7 pr-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
                </div>
                <p class="text-xs mt-1.5 h-4" x-show="message"
                   :class="found === true ? 'text-emerald-400' : (found === false ? 'text-red-400' : '')"
                   :style="found === null ? 'color: var(--text-faint);' : ''"
                   x-text="message"></p>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Badge to give</label>
                <select name="account_badge_id" required class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
                    <option value="">— Choose one of your badges —</option>
                    @foreach($mine as $b)
                        <option value="{{ $b->id }}" {{ (string) old('account_badge_id') === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50"
                    :disabled="found === false">Give badge</button>
        </form>
        @endif
    </div>

    @once
    @push('scripts')
    <script>
        function giveBadge(lookupUrl) {
            return {
                handle: @js(old('handle', '')),
                found: null,
                message: '',
                async lookup() {
                    const h = (this.handle || '').trim().replace(/^@/, '');
                    if (!h) { this.found = null; this.message = ''; return; }
                    try {
                        const res = await fetch(lookupUrl + '?handle=' + encodeURIComponent(h), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        this.found = data.found;
                        this.message = data.message || '';
                    } catch (e) {
                        this.found = null;
                        this.message = '';
                    }
                },
                onSubmit(e) {
                    if (this.found === false) { e.preventDefault(); }
                },
            };
        }
    </script>
    @endpush
    @endonce

    <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft);">
        <h2 class="text-sm font-bold mb-4" style="color: var(--text-primary);">Request a badge</h2>
        <form method="POST" action="{{ route('user.badge-requests.store') }}" x-data="{ mode: '{{ old('custom_name') ? 'custom' : 'existing' }}' }">
            @csrf
            <div class="flex flex-wrap gap-4 mb-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--text-primary);">
                    <input type="radio" name="__mode" value="existing" x-model="mode"> Pick an existing badge
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color: var(--text-primary);">
                    <input type="radio" name="__mode" value="custom" x-model="mode"> Describe a custom badge
                </label>
            </div>

            <div x-show="mode === 'existing'" class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Badge</label>
                <select name="account_badge_id" :disabled="mode !== 'existing'" class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
                    <option value="">— Choose a badge —</option>
                    @foreach($badges as $b)
                        <option value="{{ $b->id }}" @disabled(in_array($b->id, $ownedIds)) {{ (string) old('account_badge_id') === (string) $b->id ? 'selected' : '' }}>
                            {{ $b->name }}@if(in_array($b->id, $ownedIds)) (already yours)@endif
                        </option>
                    @endforeach
                </select>
                @if($badges->isEmpty())
                    <p class="text-xs mt-2" style="color: var(--text-faint);">No badges are defined yet — describe a custom one instead.</p>
                @endif
            </div>

            <div x-show="mode === 'custom'" class="mb-4" x-cloak>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Badge you want</label>
                <input type="text" name="custom_name" :disabled="mode !== 'custom'" value="{{ old('custom_name') }}" maxlength="120" placeholder="e.g. Verified Partner" class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Why should you get this badge?</label>
                <textarea name="reason" rows="4" maxlength="2000" required placeholder="Give us some context…" class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">{{ old('reason') }}</textarea>
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition">Submit request</button>
        </form>
    </div>
    </div>

    <h2 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Your requests</h2>
    @if($requests->isEmpty())
        <div class="text-center py-10 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <p style="color: var(--text-muted);">You haven't requested any badges yet.</p>
        </div>
    @else
        <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
            @foreach($requests as $r)
                @php $meta = ['pending' => ['Pending', '#f59e0b'], 'approved' => ['Approved', '#10b981'], 'rejected' => ['Rejected', '#ef4444']][$r->status] ?? ['—', '#64748b']; @endphp
                <div class="p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-sm" style="color: var(--text-primary);">
                            {{ $r->requestedLabel() }}
                            @if(!$r->account_badge_id)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,0.15); color:#f59e0b;">custom</span>@endif
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold text-white" style="background: {{ $meta[1] }};">{{ $meta[0] }}</span>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted);">{{ $r->reason }}</p>
                    @if($r->admin_notes)
                        <p class="text-xs mt-2 italic" style="color: var(--text-faint);"><i class="fas fa-comment-dots mr-1"></i> {{ $r->admin_notes }}</p>
                    @endif
                    <p class="text-[11px] mt-2" style="color: var(--text-faint);">Submitted {{ $r->created_at->diffForHumans() }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
