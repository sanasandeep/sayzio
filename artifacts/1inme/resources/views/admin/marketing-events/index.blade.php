@extends('admin.layouts.app')
@section('title', 'Marketing Events')
@section('page-title', 'Marketing Events')

@section('content')
<div class="max-w-5xl">

    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Marketing CTA click counts</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Server-side click tracking for marketing-page CTAs. Use this to see whether
                    a restyled CTA is actually moving visitors deeper into pricing, coins, or
                    premium features.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.marketing-events.index') }}" class="flex items-center gap-2">
                <label class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Window</label>
                <select name="days" onchange="this.form.submit()"
                        class="bg-black/30 border border-white/15 rounded-lg px-2.5 py-1.5 text-sm text-white ak-strong">
                    @foreach($allowedWindows as $w)
                        <option value="{{ $w }}" @selected($days === $w)>Last {{ $w }} days</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if($grandTotal === 0)
            <div class="mt-4 text-sm text-white/50 italic ak-muted">
                No marketing CTA clicks were recorded in this window.
            </div>
        @else
            <div class="mt-3 text-xs text-white/40 ak-note">
                {{ number_format($grandTotal) }} total {{ \Illuminate\Support\Str::plural('click', $grandTotal) }}
                across all tracked CTAs in the last {{ $days }} days.
            </div>
        @endif
    </div>

    @foreach($sections as $section)
        <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(110,97,255,0.15);">
                    <i class="fas fa-bullhorn text-indigo-300 ak-blue"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white/90 ak-strong">{{ $section['sourceLabel'] }}</h3>
                    <p class="text-xs text-white/50 ak-muted">
                        Source: <code class="text-white/70 ak-strong">{{ $section['source'] }}</code>
                        &middot; {{ number_format($section['total']) }} {{ \Illuminate\Support\Str::plural('click', $section['total']) }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase font-bold tracking-wider text-white/50 border-b border-white/10 ak-muted">
                            <th class="py-2 pr-4">Target</th>
                            <th class="py-2 pr-4">Slug</th>
                            <th class="py-2 pr-4 text-right">Clicks</th>
                            <th class="py-2 pr-4 text-right">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $r)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4 text-white/90 ak-strong">{{ $r['label'] }}</td>
                                <td class="py-2 pr-4 text-white/50 ak-muted"><code>{{ $r['target'] }}</code></td>
                                <td class="py-2 pr-4 text-right text-white/90 ak-strong">{{ number_format($r['count']) }}</td>
                                <td class="py-2 pr-4 text-right text-white/50 ak-muted">
                                    @if($section['total'] > 0)
                                        {{ number_format($r['pct'], 1) }}%
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

</div>
@endsection
