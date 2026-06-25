@extends('user.layouts.app')

@section('title', 'Calendar Sync')

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Calendar Sync',
        'subtitle' => 'Connect Google, Microsoft, or any CalDAV calendar to mirror events as Event links and push back changes.',
        'icon' => 'fa-calendar-alt',
        'chips' => [
            ['icon' => 'fa-database text-blue-400', 'text' => $accounts->count() . ' connected'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @php
        $hasBrokenSync = $accounts->contains(function ($a) {
            return $a->last_sync_status === 'error' || $a->last_sync_error;
        });
    @endphp
    @if($hasBrokenSync)
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.30); color: #f59e0b;">
        <i class="fas fa-triangle-exclamation mr-1.5"></i>
        One or more calendars are disconnected or returned an error on the last sync. Reconnect or hit "Sync" below to retry. Pushed event invites won't update until this is fixed.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Connect a calendar --}}
        <div class="lg:col-span-1">
            <div class="card-premium p-5">
                <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Connect a calendar</h3>
                <p class="text-xs mb-4" style="color: var(--text-muted);">Choose a provider to start the secure sign-in flow.</p>

                <a href="{{ route('user.calendar.connect', 'google') }}" class="flex items-center justify-between w-full px-4 py-3 mb-2 rounded-xl text-sm font-medium transition" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: var(--text-primary);" onmouseover="this.style.background='rgba(61,107,255,0.12)'; this.style.borderColor='rgba(61,107,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'; this.style.borderColor='rgba(255,255,255,0.08)'">
                    <span><i class="fab fa-google mr-2 text-pink-400"></i> Google Calendar</span>
                    <i class="fas fa-arrow-right text-xs opacity-50"></i>
                </a>
                <a href="{{ route('user.calendar.connect', 'microsoft') }}" class="flex items-center justify-between w-full px-4 py-3 mb-2 rounded-xl text-sm font-medium transition" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: var(--text-primary);" onmouseover="this.style.background='rgba(61,107,255,0.12)'; this.style.borderColor='rgba(61,107,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'; this.style.borderColor='rgba(255,255,255,0.08)'">
                    <span><i class="fab fa-microsoft mr-2 text-blue-400"></i> Microsoft 365 / Outlook
                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(245,158,11,.18);color:#f59e0b">Beta</span>
                    </span>
                    <i class="fas fa-arrow-right text-xs opacity-50"></i>
                </a>
                <a href="{{ route('user.calendar.connect', 'caldav') }}" class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: var(--text-primary);" onmouseover="this.style.background='rgba(61,107,255,0.12)'; this.style.borderColor='rgba(61,107,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'; this.style.borderColor='rgba(255,255,255,0.08)'">
                    <span><i class="fas fa-server mr-2 text-emerald-400"></i> CalDAV (iCloud, Fastmail…)
                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(245,158,11,.18);color:#f59e0b">Beta</span>
                    </span>
                    <i class="fas fa-arrow-right text-xs opacity-50"></i>
                </a>
            </div>

            {{-- Workspace-owner-only auto-sync default. --}}
            @if($accounts->where('push_enabled', true)->isNotEmpty())
                <div class="card-premium p-5 mt-5">
                    <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);"><i class="fas fa-bolt mr-1 text-blue-400"></i> Auto-sync new events</h3>
                    <p class="text-xs mb-3" style="color: var(--text-muted);">Pick a default calendar — every new Event Invite link you create will automatically save to it in "Keep in sync" mode.</p>
                    <form method="POST" action="{{ route('user.calendar.auto-sync') }}">
                        @csrf
                        <select name="account_id" class="w-full px-3 py-2.5 rounded-xl text-sm" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);" onchange="this.form.submit()">
                            <option value="">— Off (don't push by default) —</option>
                            @foreach($accounts->where('push_enabled', true) as $a)
                                <option value="{{ $a->id }}" {{ (int)($autoSyncAccountId ?? 0) === (int)$a->id ? 'selected' : '' }}>
                                    {{ $a->providerLabel() }} · {{ $a->display_name ?: $a->external_account_id }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif
        </div>

        {{-- Connected accounts --}}
        <div class="lg:col-span-2">
            <div class="card-premium p-5">
                <h3 class="text-base font-bold mb-4" style="color: var(--text-primary);">Connected accounts</h3>
                @if($accounts->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(92,131,255,0.18));">
                            <i class="fas fa-calendar-alt text-2xl text-blue-400"></i>
                        </div>
                        <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No calendars connected yet</p>
                        <p class="text-xs" style="color: var(--text-muted);">Pick a provider on the left to get started.</p>
                    </div>
                @else
                    <div class="overflow-x-auto -mx-5">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">
                                    <th class="text-left font-semibold pb-3 pl-5">Provider</th>
                                    <th class="text-left font-semibold pb-3">Account</th>
                                    <th class="text-left font-semibold pb-3">Last synced</th>
                                    <th class="text-left font-semibold pb-3">Sync</th>
                                    <th class="text-right font-semibold pb-3 pr-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($accounts as $a)
                                <tr style="border-top: 1px solid rgba(255,255,255,0.06);">
                                    <td class="py-3 pl-5">
                                        <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" style="background:rgba(61,107,255,.15);color:#90acff">{{ $a->provider }}</span>
                                    </td>
                                    <td class="py-3">
                                        <div class="font-semibold" style="color: var(--text-primary);">{{ $a->display_name ?: $a->external_account_id }}</div>
                                        <div class="text-xs" style="color: var(--text-muted);">{{ $a->external_account_id }}</div>
                                        @if((int)($autoSyncAccountId ?? 0) === (int)$a->id)
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(61,107,255,.18);color:#90acff">Default sync target</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-xs" style="color: var(--text-muted);">
                                        {{ $a->last_synced_at ? $a->last_synced_at->diffForHumans() : 'Never' }}
                                        @if($a->last_sync_status === 'error' || $a->last_sync_error)
                                            <div class="text-[11px] mt-0.5" style="color:#f59e0b">
                                                <i class="fas fa-triangle-exclamation mr-0.5"></i>
                                                {{ \Illuminate\Support\Str::limit($a->last_sync_error ?: 'Last sync failed', 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <form method="POST" action="{{ route('user.calendar.update', $a) }}" class="flex flex-col gap-1.5">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="mirror_enabled" value="0">
                                            <input type="hidden" name="push_enabled" value="0">
                                            <label class="inline-flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                                                <input type="checkbox" name="mirror_enabled" value="1"
                                                       {{ $a->mirror_enabled ? 'checked' : '' }} onchange="this.form.submit()"
                                                       class="rounded" style="accent-color:#3d6bff">
                                                Mirror in
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                                                <input type="checkbox" name="push_enabled" value="1"
                                                       {{ $a->push_enabled ? 'checked' : '' }} onchange="this.form.submit()"
                                                       class="rounded" style="accent-color:#3d6bff">
                                                Push out
                                            </label>
                                        </form>
                                    </td>
                                    <td class="py-3 pr-5 text-right">
                                        <form method="POST" action="{{ route('user.calendar.sync', $a) }}" class="inline-block">
                                            @csrf
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium transition" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.08)" onmouseover="this.style.background='rgba(61,107,255,.15)'" onmouseout="this.style.background='rgba(255,255,255,.06)'">
                                                <i class="fas fa-sync text-[10px]"></i> Sync
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('user.calendar.destroy', $a) }}" class="inline-block ml-1"
                                              onsubmit="return window.themedConfirmSubmit(this, {title: 'Disconnect this calendar?', message: 'Mirrored events will remain but will no longer update.', confirmText: 'Disconnect', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                                            @csrf @method('DELETE')
                                            <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)" onmouseover="this.style.background='rgba(239,68,68,.20)'" onmouseout="this.style.background='rgba(239,68,68,.10)'" title="Disconnect">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
