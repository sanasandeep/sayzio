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

    @if(session('error'))
    <div class="mb-6 p-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-exclamation-circle mr-1.5"></i>{{ session('error') }}
    </div>
    @endif

    @php
        $totalRuleHits = array_sum($ruleHits ?? []);
        $customKeywordHits = array_filter($keywordHits ?? [], function ($_, $kw) {
            return !in_array($kw, array_map('mb_strtolower', \App\Modules\User\Services\SpamChecker::BLOCKED_KEYWORDS), true);
        }, ARRAY_FILTER_USE_BOTH);
        $defaultKeywordHits = array_filter($keywordHits ?? [], function ($_, $kw) {
            return in_array($kw, array_map('mb_strtolower', \App\Modules\User\Services\SpamChecker::BLOCKED_KEYWORDS), true);
        }, ARRAY_FILTER_USE_BOTH);
    @endphp

    @if($totalRuleHits > 0)
    <div class="glass rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.15);">
                <i class="fas fa-chart-bar text-sky-400"></i>
            </div>
            <div>
                <h2 class="font-semibold" style="color: var(--text-primary);">Recent activity (last 30 days)</h2>
                <p class="text-xs" style="color: var(--text-muted);">{{ $totalRuleHits }} {{ \Illuminate\Support\Str::plural('item', $totalRuleHits) }} were flagged. Use this to spot rules or keywords that are firing too often.</p>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            @foreach([
                'blocked_keyword' => ['Blocked keyword', 'fa-key'],
                'too_many_links'  => ['Too many links',  'fa-link'],
                'rate_limit'      => ['Rate limit',      'fa-gauge-high'],
                'honeypot'        => ['Honeypot',        'fa-spider'],
            ] as $code => $meta)
                <div class="px-3 py-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    <div class="text-[10px] uppercase font-bold tracking-wider" style="color: var(--text-faint);"><i class="fas {{ $meta[1] }} mr-1"></i>{{ $meta[0] }}</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">{{ $ruleHits[$code] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        @if(!empty($customKeywordHits))
        <div class="mb-3">
            <div class="text-[10px] uppercase font-bold tracking-wider mb-2" style="color: var(--text-faint);">Your custom keywords that fired</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($customKeywordHits as $kw => $count)
                    <span class="inline-flex items-center text-xs pl-2 pr-1 py-1 rounded-lg" style="background: rgba(61,107,255,0.15); color: #bccfff;" title="Hit {{ $count }} {{ \Illuminate\Support\Str::plural('time', $count) }} in the last 30 days.">
                        <i class="fas fa-key text-[10px] mr-1 opacity-60"></i>{{ $kw }} <span class="opacity-70 ml-0.5">×{{ $count }}</span>
                        <form method="POST" action="{{ route('user.inbox.spam-settings.disable-keyword') }}" class="inline ml-1.5" onsubmit="return window.themedConfirmSubmit(this, {title: 'Stop blocking this keyword?', message: @js('Stop blocking “' . $kw . '”?'), confirmText: 'Stop blocking', confirmIcon: 'fa-times'})">
                            @csrf
                            <input type="hidden" name="keyword" value="{{ $kw }}">
                            <button type="submit" class="px-1.5 rounded hover:bg-white/10" title="Stop blocking this keyword" aria-label="Stop blocking {{ $kw }}">
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </form>
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($defaultKeywordHits))
        <div>
            <div class="text-[10px] uppercase font-bold tracking-wider mb-2" style="color: var(--text-faint);">Default keywords that fired</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($defaultKeywordHits as $kw => $count)
                    <span class="inline-flex items-center text-xs pl-2 pr-1 py-1 rounded-lg" style="background: rgba(234,88,12,0.12); color: #fdba74;" title="Hit {{ $count }} {{ \Illuminate\Support\Str::plural('time', $count) }} in the last 30 days.">
                        <i class="fas fa-shield-alt text-[10px] mr-1 opacity-60"></i>{{ $kw }} <span class="opacity-70 ml-0.5">×{{ $count }}</span>
                        <form method="POST" action="{{ route('user.inbox.spam-settings.disable-keyword') }}" class="inline ml-1.5" onsubmit="return window.themedConfirmSubmit(this, {title: 'Stop blocking default keyword?', message: @js('Stop blocking the default keyword “' . $kw . '”?'), confirmText: 'Stop blocking', confirmIcon: 'fa-times'})">
                            @csrf
                            <input type="hidden" name="keyword" value="{{ $kw }}">
                            <button type="submit" class="px-1.5 rounded hover:bg-white/10" title="Stop blocking this default keyword" aria-label="Stop blocking {{ $kw }}">
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </form>
                    </span>
                @endforeach
            </div>
            <p class="mt-2 text-[11px]" style="color: var(--text-faint);">If a default keyword keeps catching real submissions in your niche, click the × on its chip or uncheck it below.</p>
        </div>
        @endif
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 p-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-exclamation-circle mr-1.5"></i>
        {{ $errors->first() }}
    </div>
    @endif

    @if(!empty($disabledDefaults))
    <div class="glass rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(250,204,21,0.15);">
                <i class="fas fa-history text-yellow-300"></i>
            </div>
            <div>
                <h2 class="font-semibold" style="color: var(--text-primary);">Disabled default keywords</h2>
                <p class="text-xs" style="color: var(--text-muted);">Defaults you've turned off. New submissions matching these aren't flagged. Click Undo to put one back.</p>
            </div>
        </div>
        <ul class="divide-y" style="border-color: var(--border-glass);">
            @foreach($disabledDefaults as $entry)
                @php
                    $kw = $entry['keyword'];
                    $ts = $entry['disabled_at'];
                    $when = null;
                    $whenAbs = null;
                    if ($ts) {
                        try {
                            $c = \Carbon\Carbon::parse($ts);
                            $when = $c->diffForHumans();
                            $whenAbs = $c->toDayDateTimeString();
                        } catch (\Exception $e) {
                            $when = null;
                        }
                    }
                @endphp
                <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0" style="border-color: var(--border-glass);">
                    <div class="min-w-0 flex items-center gap-2">
                        <span class="inline-flex items-center text-xs px-2 py-1 rounded-lg" style="background: rgba(234,88,12,0.12); color: #fdba74;">
                            <i class="fas fa-shield-alt text-[10px] mr-1 opacity-60"></i>
                            <span class="line-through opacity-80">{{ $kw }}</span>
                        </span>
                        <span class="text-[11px] truncate" style="color: var(--text-faint);" @if($whenAbs) title="{{ $whenAbs }}" @endif>
                            @if($when)
                                Disabled {{ $when }}
                            @else
                                Disabled (date unknown)
                            @endif
                        </span>
                    </div>
                    <form method="POST" action="{{ route('user.inbox.spam-settings.enable-default-keyword') }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Re-enable default keyword?', message: @js('Re-enable the default keyword “' . $kw . '”? Future submissions matching it will be flagged again.'), confirmText: 'Re-enable', confirmIcon: 'fa-rotate-left'})">
                        @csrf
                        <input type="hidden" name="keyword" value="{{ $kw }}">
                        <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold transition hover:bg-white/10" style="background: var(--bg-glass-input); color: var(--text-secondary); border: 1px solid var(--border-glass);" title="Re-enable this default keyword">
                            <i class="fas fa-rotate-left text-[10px] mr-1"></i>Undo
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('user.inbox.spam-settings.import') }}" enctype="multipart/form-data" class="glass rounded-2xl p-6 mb-6">
        @csrf
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(56,189,248,0.15);">
                <i class="fas fa-file-csv text-sky-400"></i>
            </div>
            <div>
                <h2 class="font-semibold" style="color: var(--text-primary);">Bulk import trusted contacts</h2>
                <p class="text-xs" style="color: var(--text-muted);">Upload a CSV with <span class="font-mono">email</span> and/or <span class="font-mono">phone</span> columns. Invalid rows are skipped, phone numbers are normalized, duplicates are merged.</p>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <input type="file" name="csv" accept=".csv,text/csv,text/plain" required
                class="block w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-white/5 file:text-white hover:file:bg-white/10"
                style="color: var(--text-secondary);">
            <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold text-white whitespace-nowrap transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #0284c7, #0ea5e9);">
                <i class="fas fa-upload mr-1.5"></i>Import CSV
            </button>
        </div>
        <p class="mt-3 text-[11px]" style="color: var(--text-faint);">Recognized headers: email, e_mail, email_address, mail, phone, mobile, tel, telephone, phone_number, cell. With no header row, each cell is auto-classified.</p>
    </form>

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
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.2);">
                    <i class="fas fa-plus text-blue-400"></i>
                </div>
                <div>
                    <h2 class="font-semibold" style="color: var(--text-primary);">Your custom blocked keywords</h2>
                    <p class="text-xs" style="color: var(--text-muted);">One per line (or comma-separated). Case-insensitive substring match.</p>
                </div>
            </div>
            @if(!empty($spam['blocked_keywords'] ?? []))
            <div class="mb-3">
                <div class="text-[10px] uppercase font-bold tracking-wider mb-2" style="color: var(--text-faint);">Currently blocked — click × to stop blocking</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($spam['blocked_keywords'] as $kw)
                        <span class="inline-flex items-center text-xs pl-2 pr-1 py-1 rounded-lg" style="background: rgba(61,107,255,0.15); color: #bccfff;">
                            <i class="fas fa-key text-[10px] mr-1 opacity-60"></i>{{ $kw }}
                            <form method="POST" action="{{ route('user.inbox.spam-settings.disable-keyword') }}" class="inline ml-1.5" onsubmit="return window.themedConfirmSubmit(this, {title: 'Stop blocking this keyword?', message: @js('Stop blocking “' . $kw . '”?'), confirmText: 'Stop blocking', confirmIcon: 'fa-times'})">
                                @csrf
                                <input type="hidden" name="keyword" value="{{ $kw }}">
                                <button type="submit" class="px-1.5 rounded hover:bg-white/10" title="Stop blocking this keyword" aria-label="Stop blocking {{ $kw }}">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </form>
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
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
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">
                <i class="fas fa-save mr-1.5"></i>Save Spam Settings
            </button>
        </div>
    </form>
</div>
@endsection
