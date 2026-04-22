@extends('user.layouts.app')
@section('title', 'Blocked bots')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Blocked bots</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">
                Hits from these bot families are dropped before they reach your analytics —
                they don't count toward totals, breakdowns, exports, or the "bot hits filtered" badge.
            </p>
        </div>
        <a href="{{ url()->previous() }}" class="text-sm text-violet-500 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Currently blocked --}}
    <div class="rounded-2xl mb-6"
         style="background: var(--bg-card); border:1px solid var(--border-soft);">
        <div class="px-4 py-3 text-sm font-semibold" style="color: var(--text-primary); border-bottom:1px solid var(--border-soft);">
            <i class="fas fa-ban mr-1.5 text-rose-500"></i> Currently blocked
            <span class="ml-1 text-xs font-normal" style="color: var(--text-faint);">({{ count($blocked) }})</span>
        </div>
        @if(empty($blocked))
            <div class="px-4 py-8 text-sm text-center" style="color: var(--text-faint);">
                No bot families are blocked. Open a link's analytics page and use the bot breakdown panel to block one.
            </div>
        @else
            <ul class="divide-y" style="border-color: var(--border-soft);">
                @foreach($blocked as $family)
                    <li class="flex items-center justify-between px-4 py-3">
                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-robot mr-1.5" style="color: var(--text-faint);"></i>
                            {{ $family }}
                            @if(in_array($family, $extras, true))
                                <span class="ml-2 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded"
                                      style="background: var(--bg-glass); color: var(--text-faint);"
                                      title="This name no longer matches any current bot family. You can safely remove it.">
                                    legacy
                                </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('user.bot-blocks.destroy', ['family' => $family]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-rose-50 hover:text-rose-700 transition"
                                    style="color: var(--text-muted); border:1px solid var(--border-soft);">
                                <i class="fas fa-times mr-1"></i> Unblock
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Add a new block --}}
    <div class="rounded-2xl"
         style="background: var(--bg-card); border:1px solid var(--border-soft);">
        <div class="px-4 py-3 text-sm font-semibold" style="color: var(--text-primary); border-bottom:1px solid var(--border-soft);">
            <i class="fas fa-plus-circle mr-1.5 text-violet-500"></i> Block a bot family
        </div>
        <form method="POST" action="{{ route('user.bot-blocks.store') }}" class="px-4 py-4 flex items-center gap-3 flex-wrap">
            @csrf
            <select name="family"
                    class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg text-sm"
                    style="background: var(--bg-glass-input); border:1px solid var(--border-soft); color: var(--text-primary);"
                    required>
                <option value="">Pick a bot family…</option>
                @foreach($available as $family)
                    <option value="{{ $family }}">{{ $family }}</option>
                @endforeach
                <option disabled>──────────</option>
                <option value="Other bot">Other bot (catch-all)</option>
                <option value="Other crawler">Other crawler</option>
                <option value="Other spider">Other spider</option>
                <option value="Other scraper">Other scraper</option>
                <option value="Unknown (no UA)">Unknown (no UA)</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-violet-600 hover:bg-violet-700 text-white">
                Block
            </button>
        </form>
        <p class="px-4 pb-4 text-[11px]" style="color: var(--text-faint);">
            Blocking applies to every link you own going forward. Past hits stay in your history — only future ones are dropped.
        </p>
    </div>
</div>
@endsection
