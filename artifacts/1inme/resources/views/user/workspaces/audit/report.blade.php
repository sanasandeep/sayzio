@extends('user.layouts.app')

@section('title', 'Investigate sensitive action')

@section('content')
@php
    use App\Modules\User\Services\SensitiveActionLogger;
@endphp
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            Investigate sensitive action
        </h1>
        <p class="text-sm opacity-70 mt-1">
            Review this event in <strong>{{ $workspace->name }}</strong> and flag it
            if you didn't authorise it.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-white/10 p-6 mb-6" style="background: var(--bg-card);">
        <div class="flex items-center gap-3 mb-4">
            <span class="px-2 py-0.5 text-[11px] rounded bg-white/10 uppercase tracking-wide">
                {{ SensitiveActionLogger::label($event->action) }}
            </span>
            @if($event->reported_unauthorized_at)
                <span class="px-2 py-0.5 text-[11px] rounded bg-red-500/20 text-red-300 uppercase">
                    Flagged {{ $event->reported_unauthorized_at?->diffForHumans() }}
                </span>
            @endif
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6 text-sm">
            <div>
                <dt class="text-xs uppercase" style="color: var(--text-muted);">When</dt>
                <dd>{{ $event->occurred_at?->toDayDateTimeString() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase" style="color: var(--text-muted);">Actor</dt>
                <dd>{{ $event->actor?->name ?? $event->actor?->email ?? 'Unknown' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase" style="color: var(--text-muted);">Target</dt>
                <dd>{{ $event->target_label ?: '—' }}
                    @if($event->target_type)
                        <span class="text-xs ml-1" style="color: var(--text-muted);">({{ $event->target_type }}{{ $event->target_id ? ' #'.$event->target_id : '' }})</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase" style="color: var(--text-muted);">IP</dt>
                <dd class="font-mono">{{ $event->ip ?: '—' }}</dd>
            </div>
        </dl>

        @if(!empty($event->payload))
            <div class="mt-4">
                <div class="text-xs uppercase mb-1" style="color: var(--text-muted);">Context</div>
                <pre class="text-xs bg-black/30 p-3 rounded overflow-auto"><code>{{ json_encode($event->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
        @endif

        <div class="mt-4 text-xs" style="color: var(--text-muted);">
            Hash: <code class="font-mono">{{ substr($event->hash, 0, 20) }}…</code>
            @if($chain['ok'])
                <span class="ml-2 text-emerald-400"><i class="fas fa-check"></i> chain intact</span>
            @else
                <span class="ml-2 text-red-400"><i class="fas fa-triangle-exclamation"></i> chain mismatch at #{{ $chain['broken_at'] }}</span>
            @endif
        </div>
    </div>

    @unless($event->reported_unauthorized_at)
        <form method="post"
              action="{{ $reportPostUrl }}"
              class="rounded-xl border border-red-500/30 p-6 mb-6"
              style="background: rgba(127, 29, 29, 0.08);">
            @csrf
            <h2 class="text-lg font-semibold text-red-300 mb-2">
                <i class="fas fa-shield-halved mr-1"></i> This wasn't authorised
            </h2>
            <p class="text-sm text-gray-300 mb-4">
                Filing a report flags this event for review and notifies the workspace
                owners. The action itself can't be reversed from here — for help
                rolling it back, contact support after submitting.
            </p>
            <textarea name="note" rows="3"
                      placeholder="(Optional) Anything you can share about why this looks wrong…"
                      class="w-full px-3 py-2 rounded-lg border bg-transparent text-sm"
                      style="border-color: var(--border-strong); color: var(--text-primary);"></textarea>
            <div class="flex items-center justify-between mt-4">
                <a href="https://help.{{ parse_url(config('app.url'), PHP_URL_HOST) }}/support"
                   class="text-xs hover:text-gray-200" style="color: var(--text-faint);">Need to undo this? Contact support →</a>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">
                    File "wasn't me" report
                </button>
            </div>
        </form>
    @endunless

    @if($event->reports->isNotEmpty())
        <div class="rounded-xl border border-white/10 p-6 mb-6" style="background: var(--bg-card);">
            <h2 class="text-base font-semibold mb-3" style="color: var(--text-primary);">Reports filed</h2>
            <ul class="divide-y divide-white/5 text-sm">
                @foreach($event->reports as $r)
                    <li class="py-3">
                        <div class="flex justify-between items-baseline gap-3">
                            <div>
                                <div class="font-semibold">{{ $r->reporter?->name ?? $r->reporter_email ?? 'Recipient' }}</div>
                                @if($r->note)
                                    <div class="mt-1" style="color: var(--text-faint);">{{ $r->note }}</div>
                                @endif
                            </div>
                            <div class="text-xs" style="color: var(--text-muted);">{{ $r->created_at?->diffForHumans() }} · {{ $r->ip }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($surrounding->isNotEmpty())
        <div class="rounded-xl border border-white/10 p-6" style="background: var(--bg-card);">
            <h2 class="text-base font-semibold mb-3" style="color: var(--text-primary);">
                Surrounding activity (±6 hours)
            </h2>
            <ul class="divide-y divide-white/5 text-sm">
                @foreach($surrounding as $s)
                    <li class="py-2 flex items-baseline gap-3">
                        <span class="text-xs w-40 shrink-0" style="color: var(--text-muted);">{{ $s->occurred_at?->toDateTimeString() }}</span>
                        <span class="px-2 py-0.5 text-[10px] rounded bg-white/10 uppercase tracking-wide">
                            {{ SensitiveActionLogger::label($s->action) }}
                        </span>
                        <span style="color: var(--text-faint);">{{ $s->actor?->name ?? '—' }}</span>
                        <span class="truncate" style="color: var(--text-muted);">{{ $s->target_label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
