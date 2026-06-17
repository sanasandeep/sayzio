@extends('layouts.user')

@section('title', 'Link Insurance — '.$link->title)

@section('content')
<div class="max-w-3xl mx-auto p-6">
    <div class="mb-6">
        <a href="{{ route('user.links.edit', $link->id) }}" class="text-sm" style="color: var(--text-muted);">&larr; Back to link</a>
        <h1 class="text-2xl font-semibold mt-2">Link Insurance</h1>
        <p class="mt-1" style="color: var(--text-muted);">
            Add up to {{ $maxBackups }} backup destinations. If your primary URL goes down,
            we'll automatically redirect new clicks to the next healthy backup until the primary is back.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800">{{ session('error') }}</div>
    @endif

    <div class="mb-6 p-4 rounded border" style="background: var(--bg-card); border-color: var(--border-glass);">
        <h2 class="font-medium mb-2">Current state</h2>
        <p>
            <span class="inline-block px-2 py-1 rounded text-sm
                @if($link->insurance_state === 'primary') bg-green-100 text-green-800
                @elseif($link->insurance_state === 'failover') bg-amber-100 text-amber-800
                @else bg-red-100 text-red-800 @endif">
                {{ ucfirst($link->insurance_state) }}
            </span>
            @if ($link->insurance_state === 'failover' && $link->insurance_active_url)
                <span class="text-sm ml-2" style="color: var(--text-muted);">Serving: {{ $link->insurance_active_url }}</span>
            @endif
        </p>
        @if ($link->insurance_last_checked_at)
            <p class="text-sm mt-2" style="color: var(--text-muted);">Last checked {{ $link->insurance_last_checked_at->diffForHumans() }}</p>
        @endif

        <div class="mt-3 flex gap-2">
            <form method="POST" action="{{ route('user.links.insurance.probe', $link->id) }}">
                @csrf
                <button class="px-3 py-1.5 rounded btn-ghost text-sm">Test now</button>
            </form>
            @if ($link->insurance_state !== 'primary')
                <form method="POST" action="{{ route('user.links.insurance.restore', $link->id) }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm">Restore primary</button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.insurance.update', $link->id) }}" class="space-y-6">
        @csrf

        <div class="p-4 rounded border" style="background: var(--bg-card); border-color: var(--border-glass);">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="insurance_enabled" value="1"
                       @checked($link->insurance_enabled) class="rounded">
                <span class="font-medium">Enable Link Insurance for this link</span>
            </label>
        </div>

        <div class="p-4 rounded border grid grid-cols-1 md:grid-cols-2 gap-4" style="background: var(--bg-card); border-color: var(--border-glass);">
            <div>
                <label class="block text-sm font-medium mb-1">Check every</label>
                <select name="insurance_cadence_minutes" class="w-full border rounded px-3 py-2">
                    @foreach ($cadenceOptions as $c)
                        <option value="{{ $c }}" @selected($link->insurance_cadence_minutes == $c)>
                            {{ $c >= 60 ? ($c / 60).' hour'.($c >= 120 ? 's' : '') : $c.' minutes' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Failover after N consecutive failures</label>
                <input type="number" min="1" max="10" name="insurance_failure_threshold"
                       value="{{ $link->insurance_failure_threshold ?? 2 }}"
                       class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Restore after N consecutive successes</label>
                <input type="number" min="1" max="10" name="insurance_recovery_threshold"
                       value="{{ $link->insurance_recovery_threshold ?? 3 }}"
                       class="w-full border rounded px-3 py-2">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="insurance_auto_restore" value="1"
                           @checked($link->insurance_auto_restore ?? true)>
                    <span class="text-sm">Auto-restore primary when healthy again</span>
                </label>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Fallback message (shown if everything is down)</label>
                <input type="text" name="insurance_fallback_message" maxlength="500"
                       value="{{ old('insurance_fallback_message', $link->insurance_fallback_message) }}"
                       placeholder="Optional — leave blank to keep redirecting"
                       class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="p-4 rounded border" style="background: var(--bg-card); border-color: var(--border-glass);">
            <h2 class="font-medium mb-3">Backup destinations</h2>
            @php $existing = $link->backups; @endphp
            @for ($i = 0; $i < $maxBackups; $i++)
                @php $b = $existing[$i] ?? null; @endphp
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <input type="url" name="backups[{{ $i }}][url]"
                           value="{{ old("backups.$i.url", $b->url ?? '') }}"
                           placeholder="https://backup-{{ $i + 1 }}.example.com"
                           class="md:col-span-2 border rounded px-3 py-2">
                    <input type="text" name="backups[{{ $i }}][label]" maxlength="120"
                           value="{{ old("backups.$i.label", $b->label ?? '') }}"
                           placeholder="Label (optional)"
                           class="border rounded px-3 py-2">
                    @if ($b && $b->last_status)
                        <div class="md:col-span-3 text-xs -mt-2" style="color: var(--text-muted);">
                            Last probe: {{ $b->last_status }}
                            @if ($b->last_http_code) (HTTP {{ $b->last_http_code }}) @endif
                            @if ($b->last_checked_at) — {{ $b->last_checked_at->diffForHumans() }} @endif
                        </div>
                    @endif
                </div>
            @endfor
        </div>

        <div class="flex justify-end">
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Save settings</button>
        </div>
    </form>

    @if ($recentChecks->isNotEmpty())
        <div class="mt-8 p-4 rounded border" style="background: var(--bg-card); border-color: var(--border-glass);">
            <h2 class="font-medium mb-3">Recent probes</h2>
            <table class="w-full text-sm">
                <thead class="text-left" style="color: var(--text-muted);">
                    <tr>
                        <th class="pb-2">When</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Code</th>
                        <th>Latency</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentChecks as $c)
                        <tr class="border-t">
                            <td class="py-2">{{ $c->checked_at?->diffForHumans() }}</td>
                            <td class="truncate max-w-xs">{{ $c->target_url }}</td>
                            <td>
                                <span class="px-2 py-0.5 rounded text-xs
                                    {{ $c->status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $c->status }}
                                </span>
                            </td>
                            <td>{{ $c->http_code ?? '—' }}</td>
                            <td>{{ $c->latency_ms ? $c->latency_ms.'ms' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
