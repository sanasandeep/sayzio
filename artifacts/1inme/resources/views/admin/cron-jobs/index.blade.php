@extends('admin.layouts.app')
@section('title', 'Cron Jobs')
@section('page-title', 'Cron Jobs')

@section('content')
<div class="space-y-6" x-data="cronJobsPage()">

    <p class="text-sm text-white/50 max-w-3xl">
        Every scheduled job the platform relies on, derived live from the app's schedule
        (<code class="text-white/70">routes/console.php</code>) &mdash; so this list always stays in sync.
        These jobs only run if the single master cron entry below is configured on the server.
        This page is informational only; it never triggers or edits any schedule.
    </p>

    {{-- Scheduler health banner --}}
    @if($status['state'] === 'stale')
        <div class="rounded-2xl p-4 border border-rose-500/30 bg-rose-500/10 flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-rose-300 mt-0.5"></i>
            <div class="text-sm">
                <p class="text-rose-100 font-semibold">The scheduler appears to have stopped.</p>
                <p class="text-rose-200/70 mt-0.5">
                    No scheduled job has run since
                    <span class="font-medium text-rose-100">{{ $status['last_tick']->format('M j, H:i') }}</span>
                    ({{ $status['last_tick']->diffForHumans() }}). Jobs are no longer firing &mdash; check that the master
                    cron entry below is still present on the server.
                </p>
            </div>
        </div>
    @elseif($status['state'] === 'unknown')
        <div class="rounded-2xl p-4 border border-amber-500/30 bg-amber-500/10 flex items-start gap-3">
            <i class="fas fa-circle-exclamation text-amber-300 mt-0.5"></i>
            <div class="text-sm">
                <p class="text-amber-100 font-semibold">No scheduled job has run yet.</p>
                <p class="text-amber-200/70 mt-0.5">
                    If you just added the master cron line below it can take up to a minute to record the first run.
                    If this persists, the server crontab is most likely not configured &mdash; add the line below.
                </p>
            </div>
        </div>
    @else
        <div class="rounded-2xl p-4 border border-emerald-500/20 bg-emerald-500/[0.07] flex items-start gap-3">
            <i class="fas fa-circle-check text-emerald-300 mt-0.5"></i>
            <div class="text-sm">
                <p class="text-emerald-100 font-semibold">Scheduler is active.</p>
                <p class="text-emerald-200/70 mt-0.5">
                    Last activity {{ $status['last_tick']->diffForHumans() }} ({{ $status['last_tick']->format('M j, H:i') }}).
                    @if($status['overdue_count'] > 0)
                        <span class="text-amber-200">{{ $status['overdue_count'] }} job{{ $status['overdue_count'] === 1 ? '' : 's' }} below {{ $status['overdue_count'] === 1 ? 'is' : 'are' }} overdue &mdash; see the highlighted rows.</span>
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Master cron line --}}
    <div class="glass rounded-2xl p-6 space-y-3 border border-blue-500/20">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-300 bg-blue-500/10 border border-blue-500/20 shrink-0">
                <i class="fas fa-clock"></i>
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-white">Step 1 &mdash; Add this single line to the server crontab</h2>
                <p class="text-xs text-white/50 mt-0.5">
                    Run <code class="text-white/70">crontab -e</code> on the server and add the line below. This one entry runs
                    Laravel's scheduler every minute, which in turn fires all the jobs listed underneath at their own times.
                    Nothing else needs to be added per-job.
                </p>
            </div>
        </div>

        <div class="flex items-stretch gap-2">
            <code class="flex-1 px-3 py-2.5 bg-black/30 border border-white/10 rounded-lg text-sm text-emerald-200 font-mono break-all">{{ $masterCronLine }}</code>
            <button type="button"
                    @click="copy($refs.master.textContent, 'master')"
                    class="shrink-0 px-3 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition flex items-center gap-1.5">
                <i class="fas" :class="copied === 'master' ? 'fa-check' : 'fa-copy'"></i>
                <span x-text="copied === 'master' ? 'Copied' : 'Copy'"></span>
            </button>
        </div>
        <span x-ref="master" class="hidden">{{ $masterCronLine }}</span>
    </div>

    {{-- Jobs table --}}
    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-white">Scheduled jobs</h2>
                <p class="text-xs text-white/50 mt-0.5">{{ count($jobs) }} job{{ count($jobs) === 1 ? '' : 's' }} registered. Times shown in {{ config('app.timezone', 'UTC') }}.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                        <th class="px-6 py-3 font-semibold">Command &amp; purpose</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Frequency</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Cron</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Last ran</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Next run</th>
                        <th class="px-6 py-3 font-semibold whitespace-nowrap">Run manually</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($jobs as $job)
                        <tr class="transition align-top {{ $job['overdue'] ? 'bg-rose-500/[0.06] hover:bg-rose-500/[0.1]' : 'hover:bg-white/[0.02]' }}">
                            <td class="px-6 py-4 max-w-md">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <code class="text-blue-200 font-mono text-[13px] break-all">{{ $job['command'] }}</code>
                                    @if($job['is_callback'])
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-white/40">Closure</span>
                                    @endif
                                    @if($job['running_now'])
                                        <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">Running now</span>
                                    @endif
                                </div>
                                <p class="text-xs text-white/50 mt-1 leading-relaxed">{{ $job['purpose'] }}</p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    @if($job['without_overlapping'])
                                        <span class="text-[10px] text-white/30 flex items-center gap-1"><i class="fas fa-lock text-[9px]"></i> No overlap</span>
                                    @endif
                                    @if($job['on_one_server'])
                                        <span class="text-[10px] text-white/30 flex items-center gap-1"><i class="fas fa-server text-[9px]"></i> One server</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-white/70">{{ $job['frequency'] }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <code class="text-[12px] text-white/50 font-mono">{{ $job['expression'] }}</code>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($job['last_run'])
                                    <div class="flex items-center gap-1.5">
                                        @if($job['last_run_ok'] === false)
                                            <i class="fas fa-circle-xmark text-rose-400 text-[11px]" title="Last run failed"></i>
                                        @else
                                            <i class="fas fa-circle-check text-emerald-400 text-[11px]" title="Last run succeeded"></i>
                                        @endif
                                        <span class="text-white/80">{{ $job['last_run']->format('M j, H:i') }}</span>
                                    </div>
                                    <div class="text-[11px] text-white/40 mt-0.5">
                                        {{ $job['last_run']->diffForHumans() }}
                                        @if($job['last_runtime'] !== null)
                                            &middot; {{ rtrim(rtrim(number_format($job['last_runtime'], 2), '0'), '.') }}s
                                        @endif
                                    </div>
                                    @if($job['overdue'])
                                        <span class="inline-block mt-1 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-rose-500/15 border border-rose-400/30 text-rose-300">Overdue</span>
                                    @endif
                                    @if($job['last_run_ok'] === false && $job['last_run_error'])
                                        <div class="text-[11px] text-rose-300/70 mt-1 max-w-[16rem] truncate" title="{{ $job['last_run_error'] }}">{{ $job['last_run_error'] }}</div>
                                    @endif
                                @else
                                    <span class="text-white/30 text-[12px]">Never</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-white/60">
                                @if($job['next_run'])
                                    <div class="text-white/80">{{ $job['next_run']->format('M j, H:i') }}</div>
                                    <div class="text-[11px] text-white/40">{{ $job['next_run']->diffForHumans() }}</div>
                                @else
                                    <span class="text-white/30">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($job['manual_command'])
                                    <div class="flex items-center gap-2">
                                        <code class="text-[12px] text-white/60 font-mono break-all">{{ $job['manual_command'] }}</code>
                                        <button type="button"
                                                @click="copy('{{ addslashes($job['manual_command']) }}', '{{ $loop->index }}')"
                                                title="Copy command"
                                                class="shrink-0 w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white/60 transition flex items-center justify-center">
                                            <i class="fas text-[11px]" :class="copied === '{{ $loop->index }}' ? 'fa-check text-emerald-300' : 'fa-copy'"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-[11px] text-white/30">Runs via scheduler only</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-white/40 max-w-3xl">
        <i class="fas fa-circle-info mr-1"></i>
        Running a command manually with <code class="text-white/60">php artisan &lt;command&gt;</code> from the app directory
        (<code class="text-white/60">{{ $appPath }}</code>) executes that single job immediately, regardless of its schedule.
        Most jobs are idempotent and safe to re-run.
    </p>
</div>

@push('scripts')
<script>
    function cronJobsPage() {
        return {
            copied: null,
            async copy(text, key) {
                let ok = false;
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                        ok = true;
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                } catch (e) {
                    ok = false;
                }
                if (ok) {
                    this.copied = key;
                    setTimeout(() => { if (this.copied === key) this.copied = null; }, 1800);
                }
            },
        };
    }
</script>
@endpush
@endsection
