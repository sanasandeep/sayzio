@extends('admin.layouts.app')
@section('title', 'Biolink Reports')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-semibold text-white">Biolink Moderation Queue</h2>
                <p class="text-xs text-white/50 mt-1">Reports filed by visitors on public biolinks. Repeat reports from the same IP within 24h are coalesced.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @foreach(['pending'=>'Pending','warned'=>'Warned','hidden'=>'Hidden','escalated'=>'Escalated','dismissed'=>'Dismissed'] as $key=>$label)
                    <a href="{{ route('admin.biolink-reports.index', ['status'=>$key, 'reason'=>$reason]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs {{ $status===$key ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <a href="{{ route('admin.biolink-reports.index', ['status'=>$status]) }}"
               class="px-2.5 py-1 rounded-md text-[11px] {{ !$reason ? 'bg-white/10 text-white' : 'bg-white/5 text-white/60 hover:bg-white/10' }}">All reasons</a>
            @foreach(\App\Modules\Common\Models\BiolinkReport::REASONS as $rk=>$rl)
                <a href="{{ route('admin.biolink-reports.index', ['status'=>$status, 'reason'=>$rk]) }}"
                   class="px-2.5 py-1 rounded-md text-[11px] {{ $reason===$rk ? 'bg-violet-600/30 text-violet-200 border border-violet-500/40' : 'bg-white/5 text-white/60 hover:bg-white/10' }}">{{ $rl }}</a>
            @endforeach
        </div>

        @if($rows->count() === 0)
            <div class="text-center text-white/40 py-12 text-sm">No reports in this view.</div>
        @else
            <div class="space-y-3">
                @foreach($rows as $row)
                    @php
                        $link = $links[$row->link_id] ?? null;
                        $reports = $reportsByLink[$row->link_id] ?? collect();
                        $reasonCounts = $reports->groupBy('reason')->map->count();
                        $latest = $reports->first();
                    @endphp
                    @if($link)
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4" x-data="{ open:false, action:null }">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <a href="{{ url('/' . $link->alias) }}" target="_blank" class="text-sm font-semibold text-white hover:text-violet-300 truncate">
                                        {{ $link->title ?: ('/' . $link->alias) }}
                                    </a>
                                    <span class="text-xs text-white/40">/{{ $link->alias }}</span>
                                    @if($link->moderation_state)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 capitalize">{{ $link->moderation_state }}</span>
                                    @endif
                                    @if($link->moderation_appealed_at)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300">Appeal pending</span>
                                    @endif
                                </div>
                                <div class="text-xs text-white/50">
                                    Owner:
                                    @if($link->user)
                                        <span class="text-white/70">{{ $link->user->name }}</span>
                                        <span class="text-white/40">&lt;{{ $link->user->email }}&gt;</span>
                                    @else
                                        <span class="text-white/40">(deleted)</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 mt-2 text-[11px] text-white/60 flex-wrap">
                                    <span><i class="fas fa-flag mr-1 text-red-400"></i><strong class="text-white">{{ (int) $row->total_signals }}</strong> signals</span>
                                    <span>{{ $row->report_count }} report{{ $row->report_count==1?'':'s' }}</span>
                                    <span>last: {{ \Carbon\Carbon::parse($row->last_report_at)->diffForHumans() }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($reasonCounts as $rk => $cnt)
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-white/5 text-white/70">
                                            {{ \App\Modules\Common\Models\BiolinkReport::REASONS[$rk] ?? $rk }} · {{ $cnt }}
                                        </span>
                                    @endforeach
                                </div>
                                @if($latest && $latest->comment)
                                    <div class="mt-2 text-[12px] text-white/70 italic line-clamp-2">"{{ \Illuminate\Support\Str::limit($latest->comment, 220) }}"</div>
                                @endif
                                @if($link->moderation_appeal_message)
                                    <div class="mt-2 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-[12px] text-amber-100">
                                        <strong>Owner appeal:</strong> {{ $link->moderation_appeal_message }}
                                    </div>
                                @endif
                                <button type="button" @click="open=!open" class="mt-2 text-[11px] text-violet-300 hover:text-violet-200">
                                    <span x-text="open?'Hide all reports':'Show all {{ $reports->count() }} report(s)'"></span>
                                </button>
                                <div x-show="open" x-cloak class="mt-3 space-y-2">
                                    @foreach($reports as $r)
                                        <div class="bg-black/30 rounded-lg p-2 text-[11px] text-white/70 border border-white/5">
                                            <div class="flex justify-between mb-1">
                                                <span class="text-white/90">{{ \App\Modules\Common\Models\BiolinkReport::REASONS[$r->reason] ?? $r->reason }}</span>
                                                <span class="text-white/40">{{ $r->created_at->format('M j, g:ia') }} · IP {{ $r->reporter_ip }} · ×{{ $r->coalesced_count }}</span>
                                            </div>
                                            @if($r->comment)<div class="text-white/60 whitespace-pre-line">{{ $r->comment }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5 flex-shrink-0 w-40">
                                <form method="POST" action="{{ route('admin.biolink-reports.dismiss', $link) }}">@csrf
                                    <button type="submit" class="w-full px-3 py-1.5 bg-white/5 hover:bg-white/10 rounded-lg text-xs text-white/80">Dismiss</button>
                                </form>
                                <button type="button" @click="action = action==='warn' ? null : 'warn'"
                                    class="w-full px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 rounded-lg text-xs">Warn creator</button>
                                <button type="button" @click="action = action==='hide' ? null : 'hide'"
                                    class="w-full px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-200 rounded-lg text-xs">Hide biolink</button>
                                <button type="button" @click="action = action==='escalate' ? null : 'escalate'"
                                    class="w-full px-3 py-1.5 bg-purple-500/20 hover:bg-purple-500/30 text-purple-200 rounded-lg text-xs">Escalate</button>
                                @if($link->moderation_state)
                                    <form method="POST" action="{{ route('admin.biolink-reports.restore', $link) }}">@csrf
                                        <button type="submit" class="w-full px-3 py-1.5 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 rounded-lg text-xs">Restore</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div x-show="action" x-cloak class="mt-3 pt-3 border-t border-white/10">
                            <template x-if="action==='warn'">
                                <form method="POST" action="{{ route('admin.biolink-reports.warn', $link) }}" class="flex gap-2">@csrf
                                    <input name="note" placeholder="Note to creator (optional)" maxlength="500"
                                        class="flex-1 bg-black/30 border border-white/10 rounded-lg px-3 py-1.5 text-xs text-white">
                                    <button type="submit" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-black font-semibold rounded-lg text-xs">Confirm warn</button>
                                </form>
                            </template>
                            <template x-if="action==='hide'">
                                <form method="POST" action="{{ route('admin.biolink-reports.hide', $link) }}" class="flex gap-2">@csrf
                                    <input name="note" placeholder="Reason shown to creator (optional)" maxlength="500"
                                        class="flex-1 bg-black/30 border border-white/10 rounded-lg px-3 py-1.5 text-xs text-white">
                                    <button type="submit" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg text-xs">Confirm hide</button>
                                </form>
                            </template>
                            <template x-if="action==='escalate'">
                                <form method="POST" action="{{ route('admin.biolink-reports.escalate', $link) }}" class="flex gap-2">@csrf
                                    <input name="note" placeholder="Internal note (optional)" maxlength="500"
                                        class="flex-1 bg-black/30 border border-white/10 rounded-lg px-3 py-1.5 text-xs text-white">
                                    <button type="submit" class="px-3 py-1.5 bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-lg text-xs">Confirm escalate</button>
                                </form>
                            </template>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
            <div class="mt-5">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
