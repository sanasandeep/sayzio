@extends('admin.layouts.app')
@section('title', 'Scheduled Jobs')
@section('page-title', 'Scheduled Jobs')

@section('content')
<div class="space-y-6" x-data="scheduledJobsPage()">

    {{-- Open failure-episode banner: which jobs are in an active failure streak --}}
    @include('admin.partials.scheduled-job-failure-banner', ['failureEpisodes' => $failureEpisodes])

    <p class="text-sm text-white/50 max-w-3xl">
        Every scheduled job the platform relies on, grouped by area and derived live from the app's
        job registry &mdash; so this list always stays in sync with the code. Jobs only run if the single
        master cron entry below is configured on the server. From here you can pause or resume a job and
        run one immediately in the background; cadences themselves are defined in code and are not editable.
    </p>

    @if(session('success'))
        <div class="rounded-2xl p-4 border border-emerald-500/20 bg-emerald-500/[0.07] text-sm text-emerald-100">
            <i class="fas fa-circle-check text-emerald-300 mr-1.5"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl p-4 border border-rose-500/30 bg-rose-500/10 text-sm text-rose-100">
            <i class="fas fa-circle-xmark text-rose-300 mr-1.5"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Scheduler health banner: seeded server-side, then refreshed in place by
         the same polling loop that updates the job badges — so the banner can't
         contradict the live rows while a run is in flight. All three variants are
         rendered (only the matching one visible) so the initial state shows
         correctly even before Alpine boots. --}}
    <div x-show="status.state === 'stale'" @if($status['state'] !== 'stale') style="display: none" @endif
         class="rounded-2xl p-4 border border-rose-500/30 bg-rose-500/10 flex items-start gap-3">
        <i class="fas fa-triangle-exclamation text-rose-300 mt-0.5"></i>
        <div class="text-sm">
            <p class="text-rose-100 font-semibold">The scheduler appears to have stopped.</p>
            <p class="text-rose-200/70 mt-0.5">
                No scheduled job has run since
                <span class="font-medium text-rose-100" x-text="status.last_tick">{{ $status['state'] === 'stale' ? $status['last_tick']->format('M j, H:i') : '' }}</span>
                (<span x-text="status.last_tick_human">{{ $status['state'] === 'stale' ? $status['last_tick']->diffForHumans() : '' }}</span>). Jobs are no longer firing &mdash; check that the master
                cron entry below is still present on the server.
            </p>
        </div>
    </div>
    <div x-show="status.state === 'unknown'" @if($status['state'] !== 'unknown') style="display: none" @endif
         class="rounded-2xl p-4 border border-amber-500/30 bg-amber-500/10 flex items-start gap-3">
        <i class="fas fa-circle-exclamation text-amber-300 mt-0.5"></i>
        <div class="text-sm">
            <p class="text-amber-100 font-semibold">No scheduled job has run yet.</p>
            <p class="text-amber-200/70 mt-0.5">
                If you just added the master cron line below it can take up to a minute to record the first run.
                If this persists, the server crontab is most likely not configured &mdash; add the line below.
            </p>
        </div>
    </div>
    <div x-show="status.state === 'healthy'" @if($status['state'] !== 'healthy') style="display: none" @endif
         class="rounded-2xl p-4 border border-emerald-500/20 bg-emerald-500/[0.07] flex items-start gap-3">
        <i class="fas fa-circle-check text-emerald-300 mt-0.5"></i>
        <div class="text-sm">
            <p class="text-emerald-100 font-semibold">Scheduler is active.</p>
            <p class="text-emerald-200/70 mt-0.5">
                Last activity <span x-text="status.last_tick_human">{{ $status['state'] === 'healthy' ? $status['last_tick']->diffForHumans() : '' }}</span>
                (<span x-text="status.last_tick">{{ $status['state'] === 'healthy' ? $status['last_tick']->format('M j, H:i') : '' }}</span>).
                <span class="text-amber-200" x-show="status.overdue_count > 0" @if(!($status['state'] === 'healthy' && $status['overdue_count'] > 0)) style="display: none" @endif>
                    <span x-text="status.overdue_count">{{ $status['overdue_count'] }}</span>
                    job<span x-text="status.overdue_count === 1 ? '' : 's'">{{ $status['overdue_count'] === 1 ? '' : 's' }}</span>
                    below <span x-text="status.overdue_count === 1 ? 'is' : 'are'">{{ $status['overdue_count'] === 1 ? 'is' : 'are' }}</span>
                    overdue &mdash; see the highlighted rows.
                </span>
            </p>
        </div>
    </div>

    {{-- Master cron line --}}
    <div class="glass rounded-2xl p-6 space-y-3 border border-blue-500/20">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-300 bg-blue-500/10 border border-blue-500/20 shrink-0">
                <i class="fas fa-clock"></i>
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-white">The single line the server crontab needs</h2>
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

    {{-- Failure-alert tuning --}}
    <div class="glass rounded-2xl p-6 border border-white/10">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-rose-300 bg-rose-500/10 border border-rose-500/20 shrink-0">
                <i class="fas fa-bell"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold text-white">Job-failure alert sensitivity</h2>
                <p class="text-xs text-white/50 mt-0.5 max-w-3xl">
                    When a job fails this many times in a row (with no success in between), ops admins get an in-app + email
                    alert. If the streak keeps growing, a reminder is sent at most once per cooldown period. Lower the threshold
                    for platforms where frequent jobs matter most; raise it if occasional flakiness is expected. The scheduler
                    itself is reported as down when no job has ticked for the "scheduler down" window below. You can also mute
                    alerts for individual noisy or experimental jobs with the bell button on each row.
                </p>

                @if($errors->any())
                    <div class="mt-3 rounded-xl p-3 border border-rose-500/30 bg-rose-500/10 text-xs text-rose-200">
                        @foreach($errors->all() as $err)
                            <p><i class="fas fa-circle-xmark mr-1"></i>{{ $err }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.cron-jobs.failure-alert-settings') }}"
                      class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="fa-threshold" class="block text-[11px] uppercase tracking-wider text-white/40 font-semibold mb-1">
                            Consecutive failures before alerting
                        </label>
                        <input type="number" id="fa-threshold" name="threshold"
                               value="{{ old('threshold', $alertSettings['threshold']) }}"
                               min="{{ $alertSettings['min_threshold'] }}" max="{{ $alertSettings['max_threshold'] }}" step="1" required
                               class="w-40 px-3 py-2 bg-black/30 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-blue-400/50">
                        <p class="text-[11px] text-white/30 mt-1">{{ $alertSettings['min_threshold'] }}&ndash;{{ $alertSettings['max_threshold'] }} &middot; default {{ $alertSettings['default_threshold'] }}</p>
                    </div>
                    <div>
                        <label for="fa-cooldown" class="block text-[11px] uppercase tracking-wider text-white/40 font-semibold mb-1">
                            Reminder cooldown (hours)
                        </label>
                        <input type="number" id="fa-cooldown" name="cooldown_hours"
                               value="{{ old('cooldown_hours', $alertSettings['cooldown_hours']) }}"
                               min="{{ $alertSettings['min_cooldown_hours'] }}" max="{{ $alertSettings['max_cooldown_hours'] }}" step="1" required
                               class="w-40 px-3 py-2 bg-black/30 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-blue-400/50">
                        <p class="text-[11px] text-white/30 mt-1">{{ $alertSettings['min_cooldown_hours'] }}&ndash;{{ $alertSettings['max_cooldown_hours'] }} &middot; default {{ $alertSettings['default_cooldown_hours'] }}</p>
                    </div>
                    <div>
                        <label for="fa-stale" class="block text-[11px] uppercase tracking-wider text-white/40 font-semibold mb-1">
                            Scheduler down after (minutes)
                        </label>
                        <input type="number" id="fa-stale" name="stale_after_minutes"
                               value="{{ old('stale_after_minutes', $alertSettings['stale_after_minutes']) }}"
                               min="{{ $alertSettings['min_stale_after_minutes'] }}" max="{{ $alertSettings['max_stale_after_minutes'] }}" step="1" required
                               class="w-40 px-3 py-2 bg-black/30 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-blue-400/50">
                        <p class="text-[11px] text-white/30 mt-1">{{ $alertSettings['min_stale_after_minutes'] }}&ndash;{{ $alertSettings['max_stale_after_minutes'] }} &middot; default {{ $alertSettings['default_stale_after_minutes'] }}</p>
                    </div>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition">
                        <i class="fas fa-floppy-disk mr-1.5"></i>Save alert settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Grouped job tables --}}
    @foreach($grouped as $groupSlug => $group)
        <div class="glass rounded-2xl border border-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-white">{{ $group['label'] }}</h2>
                    <p class="text-xs text-white/50 mt-0.5">{{ count($group['jobs']) }} job{{ count($group['jobs']) === 1 ? '' : 's' }}. Times shown in {{ \App\Support\PlatformTimezone::platformDefault() }}.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                            <th class="px-6 py-3 font-semibold">Job &amp; purpose</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Frequency</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Cron</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Last ran</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Next run</th>
                            <th class="px-6 py-3 font-semibold whitespace-nowrap text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($group['jobs'] as $job)
                            @php $rowKey = $job['key'] ?? ('row-' . $groupSlug . '-' . $loop->index); @endphp
                            <tr class="transition align-top {{ ($job['overdue'] || !empty($job['failing_repeatedly'])) ? 'bg-rose-500/[0.06] hover:bg-rose-500/[0.1]' : ($job['paused'] ? 'bg-amber-500/[0.04] hover:bg-amber-500/[0.07]' : 'hover:bg-white/[0.02]') }}">
                                <td class="px-6 py-4 max-w-md">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <code class="text-blue-200 font-mono text-[13px] break-all">{{ $job['command'] }}</code>
                                        @if($job['is_callback'])
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-white/40">Closure</span>
                                        @endif
                                        @if($job['protected'])
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-500/10 border border-blue-400/30 text-blue-300" title="Critical for billing, data integrity or platform health — cannot be paused."><i class="fas fa-shield-halved text-[9px] mr-0.5"></i>Protected</span>
                                        @endif
                                        @if($job['paused'])
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-400/30 text-amber-300"><i class="fas fa-pause text-[9px] mr-0.5"></i>Paused</span>
                                        @endif
                                        @if($job['key'] && in_array($job['key'], $mutedAlertJobs, true))
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-white/40" title="Failure alerts for this job are muted — it still runs on schedule, but ops admins are not notified when it fails."><i class="fas fa-bell-slash text-[9px] mr-0.5"></i>Alerts muted</span>
                                        @endif
                                        @if($job['key'])
                                            {{-- Live badge: driven by the polling loop so it appears/disappears without a reload. --}}
                                            <span x-show="isRunning('{{ $job['key'] }}')" x-cloak
                                                  class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">
                                                <i class="fas fa-spinner fa-spin text-[9px] mr-0.5"></i>Running now
                                            </span>
                                        @endif
                                        @if($job['running_now'])
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-500/15 border border-emerald-400/30 text-emerald-300">Running now</span>
                                        @endif
                                        @if(!empty($job['failing_repeatedly']))
                                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-rose-500/15 border border-rose-400/30 text-rose-300 font-semibold" title="Every run since this job's last success has failed. Inspect the run history and error output, then fix the cause or use Run now to retry — the badge clears once the job succeeds again."><i class="fas fa-triangle-exclamation text-[9px] mr-0.5"></i>Failing repeatedly ({{ $job['failing_streak'] }} in a row)</span>
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
                                    @if($job['key'])
                                        {{-- Live cell: rendered from the polled per-job status map so the
                                             outcome of a run appears in place without a manual reload. --}}
                                        @php $jsKey = "'" . $job['key'] . "'"; @endphp
                                        <template x-if="lj({{ $jsKey }}).last_run">
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <i class="fas text-[11px]"
                                                       :class="lj({{ $jsKey }}).last_run_ok === false ? 'fa-circle-xmark text-rose-400' : 'fa-circle-check text-emerald-400'"
                                                       :title="lj({{ $jsKey }}).last_run_ok === false ? 'Last run failed' : 'Last run succeeded'"></i>
                                                    <span class="text-white/80" x-text="lj({{ $jsKey }}).last_run"></span>
                                                    <template x-if="lj({{ $jsKey }}).last_run_source === 'manual'">
                                                        <span class="text-[10px] uppercase tracking-wider px-1 py-0.5 rounded bg-white/5 border border-white/10 text-white/40" title="Triggered manually with Run now">Manual</span>
                                                    </template>
                                                </div>
                                                <div class="text-[11px] text-white/40 mt-0.5">
                                                    <span x-text="lj({{ $jsKey }}).last_run_human"></span>
                                                    <template x-if="lj({{ $jsKey }}).last_runtime !== null && lj({{ $jsKey }}).last_runtime !== undefined">
                                                        <span>&middot; <span x-text="fmtRuntime(lj({{ $jsKey }}).last_runtime)"></span>s</span>
                                                    </template>
                                                    <template x-if="lj({{ $jsKey }}).last_exit_code !== null && lj({{ $jsKey }}).last_exit_code !== undefined && lj({{ $jsKey }}).last_exit_code !== 0">
                                                        <span>&middot; exit <span x-text="lj({{ $jsKey }}).last_exit_code"></span></span>
                                                    </template>
                                                </div>
                                                <template x-if="lj({{ $jsKey }}).overdue">
                                                    <span class="inline-block mt-1 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-rose-500/15 border border-rose-400/30 text-rose-300">Overdue</span>
                                                </template>
                                                <template x-if="lj({{ $jsKey }}).last_run_ok === false && lj({{ $jsKey }}).last_run_error">
                                                    <div class="text-[11px] text-rose-300/70 mt-1 max-w-[16rem] truncate" :title="lj({{ $jsKey }}).last_run_error" x-text="lj({{ $jsKey }}).last_run_error"></div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="! lj({{ $jsKey }}).last_run">
                                            <span class="text-white/30 text-[12px]">Never</span>
                                        </template>
                                    @elseif($job['last_run'])
                                        <div class="flex items-center gap-1.5">
                                            @if($job['last_run_ok'] === false)
                                                <i class="fas fa-circle-xmark text-rose-400 text-[11px]" title="Last run failed"></i>
                                            @else
                                                <i class="fas fa-circle-check text-emerald-400 text-[11px]" title="Last run succeeded"></i>
                                            @endif
                                            <span class="text-white/80">{{ $job['last_run']->format('M j, H:i') }}</span>
                                            @if(($job['last_run_source'] ?? null) === 'manual')
                                                <span class="text-[10px] uppercase tracking-wider px-1 py-0.5 rounded bg-white/5 border border-white/10 text-white/40" title="Triggered manually with Run now">Manual</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-white/40 mt-0.5">
                                            {{ $job['last_run']->diffForHumans() }}
                                            @if($job['last_runtime'] !== null)
                                                &middot; {{ rtrim(rtrim(number_format($job['last_runtime'], 2), '0'), '.') }}s
                                            @endif
                                            @if($job['last_exit_code'] !== null && $job['last_exit_code'] !== 0)
                                                &middot; exit {{ $job['last_exit_code'] }}
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
                                    @if($job['paused'])
                                        <span class="text-amber-300/80 text-[12px]">Paused</span>
                                    @elseif($job['next_run'])
                                        <div class="text-white/80">{{ $job['next_run']->format('M j, H:i') }}</div>
                                        <div class="text-[11px] text-white/40">{{ $job['next_run']->diffForHumans() }}</div>
                                    @else
                                        <span class="text-white/30">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($job['key'])
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Run now --}}
                                            <form method="POST" action="{{ route('admin.cron-jobs.run', ['key' => $job['key']]) }}"
                                                  onsubmit="return confirm('Run {{ $job['key'] }} now, in the background?');">
                                                @csrf
                                                <button type="submit" title="Run now (in the background)"
                                                        class="w-8 h-8 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-400/20 text-emerald-300 transition flex items-center justify-center">
                                                    <i class="fas fa-play text-[11px]"></i>
                                                </button>
                                            </form>

                                            {{-- Pause / Resume --}}
                                            @if($job['paused'])
                                                <form method="POST" action="{{ route('admin.cron-jobs.resume', ['key' => $job['key']]) }}">
                                                    @csrf
                                                    <button type="submit" title="Resume this job"
                                                            class="w-8 h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-400/20 text-amber-300 transition flex items-center justify-center">
                                                        <i class="fas fa-rotate-right text-[11px]"></i>
                                                    </button>
                                                </form>
                                            @elseif(! $job['protected'])
                                                <form method="POST" action="{{ route('admin.cron-jobs.pause', ['key' => $job['key']]) }}"
                                                      onsubmit="return confirm('Pause {{ $job['key'] }}? The scheduler will skip it until you resume it.');">
                                                    @csrf
                                                    <button type="submit" title="Pause this job"
                                                            class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white/60 transition flex items-center justify-center">
                                                        <i class="fas fa-pause text-[11px]"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="w-8 h-8 rounded-lg bg-white/[0.02] border border-white/5 text-white/20 flex items-center justify-center" title="Protected — cannot be paused.">
                                                    <i class="fas fa-pause text-[11px]"></i>
                                                </span>
                                            @endif

                                            {{-- Mute / unmute failure alerts --}}
                                            @if(in_array($job['key'], $mutedAlertJobs, true))
                                                <form method="POST" action="{{ route('admin.cron-jobs.unmute-alerts', ['key' => $job['key']]) }}">
                                                    @csrf
                                                    <button type="submit" title="Alerts are muted for this job — click to re-enable failure alerts"
                                                            class="w-8 h-8 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-400/20 text-amber-300 transition flex items-center justify-center">
                                                        <i class="fas fa-bell-slash text-[11px]"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.cron-jobs.mute-alerts', ['key' => $job['key']]) }}"
                                                      onsubmit="return confirm('Mute failure alerts for {{ $job['key'] }}? It will keep running on schedule, but ops admins will no longer be notified when it fails.');">
                                                    @csrf
                                                    <button type="submit" title="Mute failure alerts for this job"
                                                            class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white/60 transition flex items-center justify-center">
                                                        <i class="fas fa-bell text-[11px]"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- History --}}
                                            <button type="button" @click="toggleHistory('{{ $job['key'] }}')" title="Recent run history"
                                                    class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white/60 transition flex items-center justify-center"
                                                    :class="historyKey === '{{ $job['key'] }}' ? 'bg-white/10 text-white' : ''">
                                                <i class="fas fa-clock-rotate-left text-[11px]"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-white/30">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                            {{-- Inline history drawer --}}
                            <tr x-show="historyKey === '{{ $job['key'] }}'" x-cloak>
                                <td colspan="6" class="px-6 pb-5 pt-0 bg-black/20">
                                    <div class="mt-3 rounded-xl border border-white/10 overflow-hidden">
                                        <div class="px-4 py-2.5 border-b border-white/10 flex items-center justify-between">
                                            <span class="text-xs font-semibold text-white/70">Recent runs &mdash; <code class="text-blue-200">{{ $job['key'] }}</code></span>
                                            <button type="button" class="text-white/40 hover:text-white/70 text-xs" @click="historyKey = null">Close</button>
                                        </div>
                                        <template x-if="historyLoading">
                                            <p class="px-4 py-3 text-xs text-white/40">Loading&hellip;</p>
                                        </template>
                                        <template x-if="! historyLoading && historyRuns.length === 0">
                                            <p class="px-4 py-3 text-xs text-white/40">No recorded runs yet. Runs are recorded from the moment this panel shipped; older executions only appear in the "Last ran" column.</p>
                                        </template>
                                        <template x-if="! historyLoading && historyRuns.length > 0">
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="text-left text-[10px] uppercase tracking-wider text-white/40 border-b border-white/10">
                                                        <th class="px-4 py-2 font-semibold">Started</th>
                                                        <th class="px-4 py-2 font-semibold">Status</th>
                                                        <th class="px-4 py-2 font-semibold">Duration</th>
                                                        <th class="px-4 py-2 font-semibold">Exit</th>
                                                        <th class="px-4 py-2 font-semibold">Source</th>
                                                        <th class="px-4 py-2 font-semibold">Error</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-white/5">
                                                    <template x-for="run in historyRuns" :key="run.id">
                                                        <tr>
                                                            <td class="px-4 py-2 whitespace-nowrap text-white/70" x-text="formatWhen(run.started_at)"></td>
                                                            <td class="px-4 py-2 whitespace-nowrap">
                                                                <span x-show="run.status === 'success'" class="text-emerald-300"><i class="fas fa-circle-check text-[10px] mr-1"></i>Success</span>
                                                                <span x-show="run.status === 'failed'" class="text-rose-300"><i class="fas fa-circle-xmark text-[10px] mr-1"></i>Failed</span>
                                                                <span x-show="run.status === 'running'" class="text-blue-300"><i class="fas fa-spinner text-[10px] mr-1"></i>Running</span>
                                                            </td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-white/60" x-text="run.runtime !== null ? (Math.round(run.runtime * 100) / 100) + 's' : '—'"></td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-white/60" x-text="run.exit_code !== null ? run.exit_code : '—'"></td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-white/60" x-text="run.source"></td>
                                                            <td class="px-4 py-2 text-rose-300/70 max-w-[18rem] truncate" :title="run.error || ''" x-text="run.error || '—'"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <p class="text-xs text-white/40 max-w-3xl">
        <i class="fas fa-circle-info mr-1"></i>
        "Run now" executes a single job immediately in the background, regardless of its schedule, and records the
        result in that job's history. You can also run any command manually with <code class="text-white/60">php artisan &lt;command&gt;</code>
        from the app directory (<code class="text-white/60">{{ $appPath }}</code>). Most jobs are idempotent and safe to re-run.
    </p>
</div>

@push('scripts')
<script>
    function scheduledJobsPage() {
        return {
            copied: null,
            historyKey: null,
            historyRuns: [],
            historyLoading: false,
            // Per-job live status map, seeded server-side and refreshed by a
            // light polling loop while any run is in flight, so badges and
            // last-run details update in place without a manual page reload.
            live: @js($liveSeed),
            // Scheduler health summary (banner), seeded server-side and
            // refreshed by the same poll so the banner never contradicts the
            // live rows.
            status: @js($statusSeed),
            // Set right after "Run now": the background run's history row may
            // not exist yet on reload, so poll through a short grace window
            // until it shows up (mirrors the mobile screen's watch window).
            watchUntil: {{ session('ran_job') ? '(Date.now() + 20000)' : 'null' }},
            pollTimer: null,
            init() {
                if (this.anyRunning() || this.watchUntil !== null) {
                    this.startPolling();
                }
            },
            lj(key) {
                return this.live[key] || {};
            },
            isRunning(key) {
                return !! this.lj(key).running_now;
            },
            anyRunning() {
                return Object.values(this.live).some((j) => j && j.running_now);
            },
            fmtRuntime(v) {
                if (v === null || v === undefined) return '';
                return String(Math.round(v * 100) / 100);
            },
            startPolling() {
                if (this.pollTimer !== null) return;
                this.pollTimer = setInterval(() => this.poll(), 3000);
            },
            stopPolling() {
                if (this.pollTimer !== null) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            },
            async poll() {
                if (document.hidden) return;
                try {
                    const res = await fetch('{{ route('admin.cron-jobs.status') }}', { headers: { 'Accept': 'application/json' } });
                    if (res.ok) {
                        const json = await res.json();
                        const jobs = json.data && json.data.jobs;
                        if (jobs && typeof jobs === 'object') {
                            this.live = jobs;
                            const status = json.data && json.data.status;
                            if (status && typeof status === 'object' && typeof status.state === 'string') {
                                this.status = status;
                            }
                            // Keep an open history drawer fresh too, quietly.
                            if (this.historyKey !== null) {
                                this.fetchRuns(this.historyKey, { quiet: true });
                            }
                        }
                    }
                } catch (e) {
                    // Transient network error — keep polling.
                }
                if (this.anyRunning()) {
                    // A run is visible now; no need for the grace window.
                    this.watchUntil = null;
                } else if (this.watchUntil === null || Date.now() > this.watchUntil) {
                    this.stopPolling();
                }
            },
            async toggleHistory(key) {
                if (this.historyKey === key) {
                    this.historyKey = null;
                    return;
                }
                this.historyKey = key;
                this.historyRuns = [];
                await this.fetchRuns(key);
            },
            async fetchRuns(key, { quiet = false } = {}) {
                if (! quiet) this.historyLoading = true;
                try {
                    const url = '{{ url('admin/cron-jobs') }}/' + encodeURIComponent(key) + '/runs';
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    if (this.historyKey === key) {
                        this.historyRuns = (json.data && json.data.runs) ? json.data.runs : [];
                    }
                } catch (e) {
                    if (this.historyKey === key && ! quiet) this.historyRuns = [];
                } finally {
                    if (this.historyKey === key && ! quiet) this.historyLoading = false;
                }
            },
            formatWhen(iso) {
                if (! iso) return '—';
                try {
                    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                } catch (e) {
                    return iso;
                }
            },
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
