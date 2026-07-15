@extends('admin.layouts.app')
@section('title', 'System Update')
@section('page-title', 'System Update')

@section('content')
<div class="max-w-2xl space-y-6" x-data="systemUpdate()" x-init="init()">

    <a href="{{ route('admin.integrations.index') }}" class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Integrations
    </a>

    {{-- Session flash --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-400 shrink-0"></i>
            <p class="text-sm text-emerald-200">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center gap-3">
            <i class="fas fa-triangle-exclamation text-red-400 shrink-0"></i>
            <p class="text-sm text-red-200">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Replit / managed mode --}}
    @if($isReplit)
    <div class="glass rounded-2xl p-6 border border-white/10">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 shrink-0 bg-blue-500/15 rounded-xl flex items-center justify-center">
                <i class="fas fa-cloud text-blue-400 text-lg"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-white">Managed by Replit</h2>
                <p class="text-sm text-white/60 mt-1">
                    This environment is hosted on Replit. Deployments are managed through the Replit platform — use
                    the <strong class="text-white/80">Publish</strong> button in your Replit workspace to deploy a
                    new version. GitHub push mirroring then keeps the repository in sync automatically.
                </p>
            </div>
        </div>
    </div>

    {{-- Not configured --}}
    @elseif(!$configured)
    <div class="glass rounded-2xl p-6 border border-white/10">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
                <i class="fas fa-key text-amber-400 text-lg"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-white">GitHub credentials not configured</h2>
                <p class="text-sm text-white/60 mt-1">
                    The update check and one-click deploy require a <code class="px-1 py-0.5 rounded bg-black/30">GITHUB_TOKEN</code>
                    and <code class="px-1 py-0.5 rounded bg-black/30">GITHUB_REPO</code> in your
                    <span class="text-white/80">EC2 <code>.env</code></span> file. Set them to enable this feature.
                </p>
                <div class="mt-3 space-y-1 text-xs font-mono text-white/50 bg-black/20 rounded-lg p-3">
                    <p>GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx</p>
                    <p>GITHUB_REPO=sanasandeep/sayzio</p>
                </div>
                <p class="text-xs text-white/40 mt-2">
                    The token needs <strong class="text-white/60">actions:write</strong> (or the <em>workflow</em>) scope
                    to dispatch the deploy workflow. A fine-grained token with contents:read + actions:write works well.
                </p>
            </div>
        </div>
    </div>

    {{-- Configured + EC2 --}}
    @else

    {{-- In-progress state (polling) --}}
    @if($inProgress)
    <div class="p-4 rounded-xl border flex items-center gap-3" style="border-color:rgba(59,130,246,0.4); background:rgba(59,130,246,0.08);" x-show="pollActive">
        <i class="fas fa-spinner fa-spin text-blue-400 shrink-0"></i>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-blue-200">Deploy in progress…</p>
            <p class="text-xs text-blue-300/70 mt-0.5" x-text="runStatus">Waiting for GitHub Actions to pick up the job…</p>
        </div>
        <a :href="runUrl" x-show="runUrl" target="_blank" rel="noopener"
           class="ml-auto shrink-0 inline-flex items-center gap-1.5 text-xs text-blue-300 hover:text-blue-200">
            <i class="fas fa-external-link-alt"></i> View run
        </a>
    </div>
    <div x-show="!pollActive && deployDone" class="p-4 rounded-xl border"
         :class="deploySuccess ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-red-500/30 bg-red-500/10'">
        <p class="text-sm font-semibold" :class="deploySuccess ? 'text-emerald-200' : 'text-red-200'"
           x-text="deploySuccess ? 'Deploy completed successfully!' : 'Deploy failed — check GitHub Actions for details.'"></p>
    </div>
    @endif

    {{-- Update status card --}}
    @if($status)
    @php
        $hasUpdate = !empty($status['available']);
        $error     = $status['error'] ?? null;
    @endphp

    <div class="glass rounded-2xl border overflow-hidden"
         style="border-color: {{ $hasUpdate ? 'rgba(59,130,246,0.35)' : 'rgba(255,255,255,0.1)' }}">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-white/10">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background: {{ $hasUpdate ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.1)' }}">
                <i class="fas {{ $hasUpdate ? 'fa-circle-up text-blue-400' : 'fa-check-circle text-emerald-400' }} text-base"></i>
            </div>
            <div class="min-w-0 flex-1">
                @if($error === 'not_configured')
                    <p class="text-sm font-semibold text-white">Not configured</p>
                @elseif($error === 'local_git_unavailable')
                    <p class="text-sm font-semibold text-white">Cannot read local git state</p>
                @elseif($error === 'github_api_error')
                    <p class="text-sm font-semibold text-white">GitHub API unreachable</p>
                @elseif($hasUpdate)
                    <p class="text-sm font-semibold text-blue-200">
                        Update available
                        @if(!empty($status['commits_behind']))
                            &mdash; {{ $status['commits_behind'] }}
                            {{ \Illuminate\Support\Str::plural('commit', $status['commits_behind']) }} behind
                        @endif
                    </p>
                @else
                    <p class="text-sm font-semibold text-emerald-200">Up to date</p>
                @endif
                @if($status['checked_at'])
                    <p class="text-xs text-white/40 mt-0.5">Checked {{ \Carbon\Carbon::parse($status['checked_at'])->diffForHumans() }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.system-update.refresh') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70 transition">
                    <i class="fas fa-rotate-right"></i> Refresh
                </button>
            </form>
        </div>

        <div class="divide-y divide-white/5">
            {{-- Current commit --}}
            <div class="px-5 py-3 flex items-start gap-3">
                <span class="text-xs text-white/40 shrink-0 w-28 pt-0.5">Deployed commit</span>
                <div class="min-w-0">
                    @if($status['local_sha'])
                        <code class="text-xs font-mono text-white/70">{{ substr($status['local_sha'], 0, 12) }}</code>
                    @else
                        <span class="text-xs text-white/40 italic">unavailable</span>
                    @endif
                </div>
            </div>

            {{-- Latest remote commit --}}
            @if($status['remote_sha'])
            <div class="px-5 py-3 flex items-start gap-3">
                <span class="text-xs text-white/40 shrink-0 w-28 pt-0.5">Latest on main</span>
                <div class="min-w-0 space-y-0.5">
                    <code class="text-xs font-mono {{ $hasUpdate ? 'text-blue-300' : 'text-white/70' }}">{{ substr($status['remote_sha'], 0, 12) }}</code>
                    @if($status['remote_message'])
                        <p class="text-xs text-white/50 truncate max-w-xs">{{ $status['remote_message'] }}</p>
                    @endif
                    @if($status['remote_date'])
                        <p class="text-xs text-white/30">{{ \Carbon\Carbon::parse($status['remote_date'])->diffForHumans() }}
                            @if($status['remote_author'])&mdash; {{ $status['remote_author'] }}@endif
                        </p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Deploy action --}}
        @if($hasUpdate && !$inProgress)
        <div class="px-5 py-4 border-t border-white/10 flex items-center gap-3">
            <form method="POST" action="{{ route('admin.system-update.deploy') }}"
                  onsubmit="return confirm('Trigger the GitHub Actions \'Deploy to EC2\' workflow now? This will pull the latest code from GitHub and restart services on the EC2 server.');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition"
                        onclick="this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Dispatching…'; this.form.submit();">
                    <i class="fas fa-rocket"></i> Update now
                </button>
            </form>
            <a href="https://github.com/{{ config('services.github.repo') }}/actions/workflows/{{ \App\Services\Integrations\SystemUpdateService::WORKFLOW_FILE }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
                <i class="fas fa-external-link-alt"></i> Open GitHub Actions
            </a>
        </div>
        @elseif($inProgress)
        <div class="px-5 py-4 border-t border-white/10">
            <button disabled
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600/40 text-white/50 cursor-not-allowed">
                <i class="fas fa-spinner fa-spin"></i> Deploy in progress…
            </button>
        </div>
        @elseif(!$hasUpdate)
        <div class="px-5 py-4 border-t border-white/10 flex items-center gap-3">
            <a href="https://github.com/{{ config('services.github.repo') }}/actions/workflows/{{ \App\Services\Integrations\SystemUpdateService::WORKFLOW_FILE }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
                <i class="fas fa-external-link-alt"></i> View GitHub Actions
            </a>
            <span class="text-white/20">·</span>
            <form method="POST" action="{{ route('admin.system-update.deploy') }}"
                  onsubmit="return confirm('Force a deploy even though the server appears up to date? This will re-run the full GitHub Actions deploy pipeline.');">
                @csrf
                <button type="submit" class="text-xs text-white/40 hover:text-white/70">
                    Force re-deploy
                </button>
            </form>
        </div>
        @endif
    </div>
    @endif

    {{-- Last deploy audit --}}
    @if($lastAudit)
    <div class="glass rounded-xl p-4 border border-white/10">
        <p class="text-xs text-white/40 uppercase tracking-wider mb-2">Last triggered deploy</p>
        <div class="flex items-center gap-4 text-xs text-white/60">
            <span><i class="fas fa-user mr-1 text-white/30"></i> {{ $lastAudit['triggered_by'] }}</span>
            <span><i class="fas fa-clock mr-1 text-white/30"></i> {{ \Carbon\Carbon::parse($lastAudit['triggered_at'])->diffForHumans() }}</span>
            <span class="capitalize"><i class="fas fa-circle-dot mr-1 text-white/30"></i> {{ $lastAudit['status'] }}</span>
        </div>
    </div>
    @endif

    {{-- Latest run details (visible even when up to date, after a deploy) --}}
    @if($latestRun)
    <div class="glass rounded-xl p-4 border border-white/10">
        <p class="text-xs text-white/40 uppercase tracking-wider mb-2">Latest workflow run</p>
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-xs text-white/60">
                <span class="capitalize">{{ str_replace('_', ' ', $latestRun['status']) }}</span>
                @if($latestRun['conclusion'])
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                        {{ $latestRun['conclusion'] === 'success' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300' }}">
                        {{ $latestRun['conclusion'] }}
                    </span>
                @endif
                <span class="text-white/40">{{ \Carbon\Carbon::parse($latestRun['created_at'])->diffForHumans() }}</span>
            </div>
            <a href="{{ $latestRun['html_url'] }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
                <i class="fas fa-external-link-alt"></i> View run
            </a>
        </div>
    </div>
    @endif

    {{-- How it works note --}}
    <div class="text-xs text-white/40 space-y-1 leading-relaxed border border-white/5 rounded-xl p-4 bg-white/2">
        <p class="font-semibold text-white/50">How it works</p>
        <p>
            "Update now" dispatches the <code class="px-1 rounded bg-black/20">deploy-ec2.yml</code> GitHub Actions
            workflow via <code class="px-1 rounded bg-black/20">workflow_dispatch</code>. GitHub then SSHs into the
            EC2 server and runs <code class="px-1 rounded bg-black/20">deploy.sh</code> — the same script used by
            automatic push-to-deploy — pulling the latest code, rebuilding assets, running migrations, and reloading
            services. The result appears in the GitHub Actions run log.
        </p>
        <p class="mt-1">
            Only one deploy can run at a time. A 30-minute lock prevents double-triggers. Automatic push-to-deploy
            on <code class="px-1 rounded bg-black/20">git push main</code> remains unchanged.
        </p>
    </div>

    @endif {{-- end configured --}}

</div>
@endsection

@push('scripts')
<script>
function systemUpdate() {
    return {
        pollActive: {{ $inProgress ? 'true' : 'false' }},
        pollInterval: null,
        runStatus: 'Waiting for GitHub Actions to pick up the job…',
        runUrl: '',
        deployDone: false,
        deploySuccess: false,

        init() {
            if (this.pollActive) {
                this.startPolling();
            }
        },

        startPolling() {
            this.pollInterval = setInterval(() => this.poll(), 8000);
        },

        async poll() {
            try {
                const res  = await fetch('{{ route('admin.system-update.status') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.latest_run) {
                    const run = data.latest_run;
                    this.runUrl = run.html_url || '';
                    if (run.status === 'queued') {
                        this.runStatus = 'Queued — waiting for a runner…';
                    } else if (run.status === 'in_progress') {
                        this.runStatus = 'Running on GitHub Actions…';
                    } else if (run.status === 'completed') {
                        this.runStatus = run.conclusion === 'success'
                            ? 'Completed successfully — the server has been updated.'
                            : 'Completed with errors — check GitHub Actions for details.';
                    }
                }

                if (!data.in_progress) {
                    clearInterval(this.pollInterval);
                    this.pollActive = false;
                    this.deployDone = true;
                    this.deploySuccess = data.latest_run?.conclusion === 'success';
                }
            } catch (e) {
                // network error — keep polling
            }
        }
    }
}
</script>
@endpush
