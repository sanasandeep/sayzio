{{--
    Red at-a-glance banner for open scheduled-job failure episodes. Reads the
    same `scheduled_job_health` alert state ScheduledJobHealthAlerts maintains
    (one open episode per failure streak + the stale-scheduler episode), so it
    always agrees with the one-notification-per-streak ops alerts. Shown on
    both /admin/cron-jobs and the admin dashboard (mirroring the SchemaHealth
    banner pattern). Expects: $failureEpisodes = ScheduledJobHealthAlerts::openEpisodes().
--}}
@php
    $failingJobs = $failureEpisodes['jobs'] ?? [];
    $schedulerEpisode = $failureEpisodes['scheduler'] ?? null;
    $episodeCount = count($failingJobs) + ($schedulerEpisode !== null ? 1 : 0);
    $fmtSince = function ($iso) {
        try {
            return $iso ? \Illuminate\Support\Carbon::parse($iso)->diffForHumans() . ' (' . \App\Support\PlatformTimezone::format(\Illuminate\Support\Carbon::parse($iso), 'M j, H:i', false) . ')' : null;
        } catch (\Throwable $e) {
            return null;
        }
    };
@endphp
@if($episodeCount > 0)
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);" data-testid="banner-scheduled-job-failures">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-triangle-exclamation text-red-400 text-lg ak-red"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-red-300 ak-red">
                {{ $episodeCount }} scheduled {{ \Illuminate\Support\Str::plural('job', $episodeCount) }} {{ $episodeCount === 1 ? 'is' : 'are' }} currently failing
            </h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                {{ count($failingJobs) === 1 && $schedulerEpisode === null ? 'This job has' : 'These jobs have' }} an open failure streak &mdash; the last run finished
                with an error and no successful run has closed the episode yet. Ops admins were alerted once when each streak
                opened; an all-clear follows the next successful run. Use <span class="text-red-200 font-medium ak-red">Run now</span>
                on the job below to retry it immediately.
            </p>
            <ul class="mt-3 space-y-2">
                @if($schedulerEpisode !== null)
                    <li class="text-sm">
                        <span class="font-mono font-semibold text-red-200 ak-red">scheduler heartbeat</span>
                        <span class="text-white/50 ak-muted">&mdash; the scheduler itself appears to be down (no jobs are firing at all)</span>
                        @if($fmtSince($schedulerEpisode['since'] ?? null))
                            <span class="text-white/40 text-xs block sm:inline sm:ml-1 ak-note">alerted {{ $fmtSince($schedulerEpisode['since']) }}</span>
                        @endif
                    </li>
                @endif
                @foreach($failingJobs as $episode)
                    <li class="text-sm">
                        <span class="font-mono font-semibold text-red-200 ak-red">{{ $episode['key'] }}</span>
                        @if($fmtSince($episode['since'] ?? null))
                            <span class="text-white/40 text-xs sm:ml-1 ak-note">failing since {{ $fmtSince($episode['since']) }}</span>
                        @endif
                        @if(!empty($episode['last_error']))
                            <span class="block text-xs text-white/50 font-mono mt-0.5 break-all ak-muted">{{ \Illuminate\Support\Str::limit($episode['last_error'], 200) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if(!request()->routeIs('admin.cron-jobs.*'))
                <a href="{{ route('admin.cron-jobs.index') }}"
                   class="mt-3 inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200 underline ak-red">
                    <i class="fas fa-arrow-right"></i> Open the Scheduled Jobs panel
                </a>
            @endif
        </div>
    </div>
</div>
@endif
