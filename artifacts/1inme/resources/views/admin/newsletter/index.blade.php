@extends('admin.layouts.app')
@section('title', 'Newsletter Subscribers')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white mb-1">Newsletter Subscribers</h2>
                <p class="text-sm text-white/50">
                    {{ number_format($totals['active']) }} active · {{ number_format($totals['all']) }} total
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.newsletter.compose') }}"
                   class="px-3 py-2 bg-emerald-500/15 border border-emerald-400/30 hover:bg-emerald-500/25 rounded-lg text-xs text-emerald-100">
                    <i class="fas fa-paper-plane mr-1"></i> Compose &amp; send
                </a>
                <a href="{{ route('admin.newsletter.export') }}"
                   class="px-3 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-xs text-white">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </a>
            </div>
        </div>

        <div class="mb-4 p-4 bg-white/[0.02] border border-white/10 rounded-xl">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-white">Opt-out sources</h3>
                    <p class="text-[11px] text-white/40">Last {{ $optOutBreakdown['window_days'] }} days · {{ number_format($optOutBreakdown['total']) }} unsubscribe{{ $optOutBreakdown['total'] === 1 ? '' : 's' }}</p>
                </div>
                <div class="flex items-center gap-3 text-[11px] text-white/60">
                    <span class="flex items-center gap-1.5"><span class="inline-block w-2 h-2 rounded-full bg-sky-400"></span>Inbox one-click</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>Footer link</span>
                    @if($optOutBreakdown['unknown']['count'] > 0)
                        <span class="flex items-center gap-1.5"><span class="inline-block w-2 h-2 rounded-full bg-white/30"></span>Unknown</span>
                    @endif
                </div>
            </div>

            @if($optOutBreakdown['total'] > 0)
                <div class="flex w-full h-2 rounded-full overflow-hidden bg-white/5 mb-3" role="img"
                     aria-label="Opt-out sources: {{ $optOutBreakdown['inbox']['pct'] }}% inbox one-click, {{ $optOutBreakdown['footer']['pct'] }}% footer link@if($optOutBreakdown['unknown']['count'] > 0), {{ $optOutBreakdown['unknown']['pct'] }}% unknown@endif">
                    @if($optOutBreakdown['inbox']['count'] > 0)
                        <div class="bg-sky-400 h-full" style="width: {{ $optOutBreakdown['inbox']['pct'] }}%"></div>
                    @endif
                    @if($optOutBreakdown['footer']['count'] > 0)
                        <div class="bg-amber-400 h-full" style="width: {{ $optOutBreakdown['footer']['pct'] }}%"></div>
                    @endif
                    @if($optOutBreakdown['unknown']['count'] > 0)
                        <div class="bg-white/30 h-full" style="width: {{ $optOutBreakdown['unknown']['pct'] }}%"></div>
                    @endif
                </div>
                <div class="grid grid-cols-2 @if($optOutBreakdown['unknown']['count'] > 0) sm:grid-cols-3 @endif gap-3">
                    <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                        <div class="text-xs text-white/50">Inbox one-click</div>
                        <div class="text-base font-semibold text-white">{{ $optOutBreakdown['inbox']['pct'] }}%
                            <span class="text-xs font-normal text-white/40">· {{ number_format($optOutBreakdown['inbox']['count']) }}</span>
                        </div>
                    </div>
                    <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                        <div class="text-xs text-white/50">Footer link</div>
                        <div class="text-base font-semibold text-white">{{ $optOutBreakdown['footer']['pct'] }}%
                            <span class="text-xs font-normal text-white/40">· {{ number_format($optOutBreakdown['footer']['count']) }}</span>
                        </div>
                    </div>
                    @if($optOutBreakdown['unknown']['count'] > 0)
                        <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                            <div class="text-xs text-white/50">Unknown</div>
                            <div class="text-base font-semibold text-white">{{ $optOutBreakdown['unknown']['pct'] }}%
                                <span class="text-xs font-normal text-white/40">· {{ number_format($optOutBreakdown['unknown']['count']) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-xs text-white/40">No opt-outs in the last {{ $optOutBreakdown['window_days'] }} days.</p>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="mb-4">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search by email…"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Source</th>
                        <th class="py-2 pr-3">Subscribed</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($subscribers as $s)
                        <tr class="text-white/80">
                            <td class="py-2 pr-3 font-mono text-xs text-white">{{ $s->email }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ $s->source ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ optional($s->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="py-2 pr-3 text-xs">
                                @if($s->unsubscribed_at)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50 inline-block w-fit">
                                            unsubscribed {{ $s->unsubscribed_at->format('Y-m-d') }}
                                        </span>
                                        @if($s->unsubscribe_source === 'inbox')
                                            <span class="text-[10px] text-white/40" title="One-click unsubscribe from the inbox provider (Gmail/Apple Mail) per RFC 8058">
                                                via inbox one-click
                                            </span>
                                        @elseif($s->unsubscribe_source === 'footer')
                                            <span class="text-[10px] text-white/40" title="Recipient clicked the unsubscribe link in the email footer">
                                                via footer link
                                            </span>
                                        @else
                                            <span class="text-[10px] text-white/30">source unknown</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-200">active</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $s) }}"
                                      onsubmit="return confirm('Delete this subscriber?');" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-300 hover:text-red-200"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-white/40 text-sm">No subscribers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $subscribers->links() }}</div>
    </div>
</div>
@endsection
