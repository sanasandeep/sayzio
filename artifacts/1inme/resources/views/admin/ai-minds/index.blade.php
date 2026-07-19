@extends('admin.layouts.app')
@section('title', 'AI Knowledge Bases')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Knowledge Bases</h1>
            <p class="text-sm text-white/50 mt-1">Per-user knowledge bases and the platform default mind.</p>
        </div>
        <form method="POST" action="{{ route('admin.ai-minds.reseed') }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Re-queue every source?', message: 'Ingestion will be re-queued for every source on the platform default Mind.', confirmText: 'Re-queue', confirmIcon: 'fa-rotate', iconClass: 'fa-rotate'})">
            @csrf
            <button class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm"><i class="fas fa-rotate"></i> Re-seed default</button>
        </form>
    </div>

    {{-- Aggregate stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            ['label'=>'Minds','val'=>$totals['minds'],'tint'=>'violet'],
            ['label'=>'Sources','val'=>$totals['sources'],'tint'=>'cyan'],
            ['label'=>'Chunks','val'=>$totals['chunks'],'tint'=>'emerald'],
            ['label'=>'Failed sources','val'=>$totals['failed'],'tint'=>'red'],
            ['label'=>'Disabled minds','val'=>$totals['disabled'],'tint'=>'amber'],
        ] as $card)
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <p class="text-[10px] uppercase tracking-wider text-white/40">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold text-{{ $card['tint'] }}-300 mt-1">{{ number_format($card['val']) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Platform default Mind: built-in answer freshness --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-white font-semibold flex items-center gap-2">
                <i class="fas fa-book text-cyan-300"></i> Built-in assistant answers
            </h3>
            <span class="text-[11px] uppercase tracking-wider text-white/40">Platform default Mind</span>
        </div>
        <p class="text-xs text-white/40 mb-3">Whether the live copy matches the current product docs, and when it was last re-embedded. Use “Re-seed default” above if anything is drifted or stuck.</p>
        @if(empty($staticSources))
            <p class="text-sm text-white/40">The platform default Mind isn’t seeded yet. Click “Re-seed default” to create it.</p>
        @else
            <table class="w-full text-sm text-left">
                <thead class="text-[11px] uppercase tracking-wider text-white/40">
                    <tr>
                        <th class="py-2">Source</th>
                        <th>Sync</th>
                        <th>Ingest status</th>
                        <th class="text-right">Last ingested</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($staticSources as $s)
                        <tr class="text-white/80">
                            <td class="py-2">
                                {{ $s['title'] }}
                                <span class="text-[10px] uppercase tracking-wider text-white/30 ml-1">{{ $s['type'] }}</span>
                            </td>
                            <td>
                                @if(!$s['exists'])
                                    <span class="inline-flex items-center gap-1 text-white/40 text-xs"><i class="fas fa-circle-minus"></i> Not created</span>
                                @elseif($s['in_sync'])
                                    <span class="inline-flex items-center gap-1 text-emerald-300 text-xs"><i class="fas fa-circle-check"></i> In sync</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-amber-300 text-xs"><i class="fas fa-triangle-exclamation"></i> Drifted</span>
                                @endif
                            </td>
                            <td>
                                @php $st = $s['status']; @endphp
                                @if(!$st)
                                    <span class="text-white/40 text-xs">-</span>
                                @elseif($st === \App\Modules\User\Models\AiMindSource::STATUS_READY)
                                    <span class="text-emerald-300 text-xs">Ready</span>
                                @elseif($st === \App\Modules\User\Models\AiMindSource::STATUS_FAILED)
                                    <span class="text-red-300 text-xs">Failed</span>
                                    @if($s['status_message'])<p class="text-[11px] text-white/40 max-w-[16rem] truncate">{{ $s['status_message'] }}</p>@endif
                                @else
                                    <span class="text-cyan-300 text-xs">{{ ucfirst($st) }}</span>
                                @endif
                            </td>
                            <td class="text-right text-white/60 text-xs">
                                @if($s['last_ingested_at'])
                                    <span title="{{ $s['last_ingested_at']->toDayDateTimeString() }}">{{ $s['last_ingested_at']->diffForHumans() }}</span>
                                @else
                                    <span class="text-white/30">Never</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Caps form --}}
    <form method="POST" action="{{ route('admin.ai-minds.caps.update') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold">Caps</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($caps as $k => $v)
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/40">{{ str_replace('_',' ', $k) }}</label>
                    <input type="number" min="0" name="caps[{{ $k }}]" value="{{ old('caps.'.$k, $v) }}"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                </div>
            @endforeach
        </div>
        <div class="flex justify-end"><button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm">Save caps</button></div>
    </form>

    {{-- Global daily AI credit spend trend --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2">
                <i class="fas fa-coins text-amber-300"></i> Daily Mind credit spend
            </h3>
            <span class="text-[11px] uppercase tracking-wider text-white/40">Last 30 days · all minds</span>
        </div>
        <x-mind-daily-spend-chart :days="$dailySpend" height="h-32" />
    </div>

    {{-- Top minds by AI credit spend --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-white font-semibold">Top 10 Minds by credit spend</h3>
            <span class="text-[11px] uppercase tracking-wider text-white/40">Last 30 days</span>
        </div>
        @if($topByCredits->isEmpty())
            <p class="text-sm text-white/40">No Mind credit spend in the last 30 days.</p>
        @else
            <table class="w-full text-sm text-left">
                <thead class="text-[11px] uppercase tracking-wider text-white/40">
                    <tr>
                        <th class="py-2">Mind</th>
                        <th>Owner</th>
                        <th class="text-right">Ingestion</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($topByCredits as $row)
                        <tr class="text-white/80">
                            <td class="py-2">
                                {{ $row['mind']->name }}
                                @if($row['mind']->isPlatform())<span class="text-[10px] uppercase tracking-wider text-cyan-300/80 ml-1">Default</span>@endif
                            </td>
                            <td>
                                {{ $row['mind']->user?->name ?: 'platform' }}
                                <br><span class="text-[11px] text-white/40">{{ $row['mind']->user?->email }}</span>
                            </td>
                            <td class="text-right text-cyan-300">{{ number_format($row['ingest']) }}</td>
                            <td class="text-right text-blue-300">{{ number_format($row['query']) }}</td>
                            <td class="text-right font-semibold text-white">{{ number_format($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Top users --}}
    @if($topUsers->isNotEmpty())
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h3 class="text-white font-semibold mb-3">Top users by indexed chunks</h3>
        <table class="w-full text-sm text-left">
            <thead class="text-[11px] uppercase tracking-wider text-white/40">
                <tr><th class="py-2">User #</th><th>Minds</th><th>Sources</th><th>Chunks</th></tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach($topUsers as $row)
                    <tr class="text-white/80">
                        <td class="py-2">#{{ $row->user_id }}</td>
                        <td>{{ $row->minds_count }}</td>
                        <td>{{ (int) $row->sources_total }}</td>
                        <td>{{ (int) $row->chunks_total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- All minds --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h3 class="text-white font-semibold mb-3">All minds</h3>
        <table class="w-full text-sm text-left">
            <thead class="text-[11px] uppercase tracking-wider text-white/40">
                <tr>
                    <th class="py-2">Name</th><th>Owner</th><th>Sources</th><th>Chunks</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach($minds as $m)
                    <tr class="text-white/80">
                        <td class="py-2">{{ $m->name }} @if($m->isPlatform())<span class="text-[10px] uppercase tracking-wider text-cyan-300/80 ml-1">Default</span>@endif</td>
                        <td>{{ $m->user?->name ?: 'platform' }}<br><span class="text-[11px] text-white/40">{{ $m->user?->email }}</span></td>
                        <td>{{ $m->sources_count }}</td>
                        <td>{{ $m->chunks_count }}</td>
                        <td>
                            @if($m->is_disabled)
                                <span class="text-red-300 text-xs">Disabled</span>
                                <p class="text-[11px] text-white/40 max-w-[16rem] truncate">{{ $m->disabled_reason }}</p>
                            @else
                                <span class="text-emerald-300 text-xs">Active</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($m->is_disabled)
                                <form method="POST" action="{{ route('admin.ai-minds.enable', $m) }}">@csrf
                                    <button class="text-xs text-emerald-300 hover:underline">Enable</button>
                                </form>
                            @elseif(!$m->isPlatform())
                                <form method="POST" action="{{ route('admin.ai-minds.disable', $m) }}" class="flex gap-1 items-center" onsubmit="return window.themedConfirmSubmit(this, {title: 'Disable this Mind?', confirmText: 'Disable', confirmIcon: 'fa-ban', iconClass: 'fa-ban'})">
                                    @csrf
                                    <input name="reason" required maxlength="500" placeholder="Reason" class="bg-white/[0.04] border border-white/10 rounded px-2 py-1 text-xs text-white w-40">
                                    <button class="text-xs text-red-300 hover:underline">Disable</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-3">{{ $minds->links() }}</div>
    </div>
</div>
@endsection
