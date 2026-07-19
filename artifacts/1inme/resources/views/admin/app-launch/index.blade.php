@extends('admin.layouts.app')
@section('title', 'App Launch Signups')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white mb-1">App Launch Signups</h2>
                <p class="text-sm text-white/50">
                    Visitors who asked to be notified when the mobile app hits the stores ·
                    {{ number_format($totals['all']) }} total
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.app-launch.export') }}"
                   class="px-3 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-xs text-white">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </a>
                @if($totals['pending'] > 0)
                    <form method="POST" action="{{ route('admin.app-launch.notify') }}" class="inline"
                          onsubmit="return window.themedConfirmSubmit(this, {
                              title: 'Announce the launch now?',
                              message: @js(
                                  ($isLaunched
                                      ? 'This will email the launch announcement to ' . number_format($totals['pending']) . ' ' . ($totals['pending'] === 1 ? 'signup who has' : 'signups who have') . ' not been notified yet, with the live store link(s). Already-notified signups are skipped.'
                                      : 'Warning: no store URL is configured yet. Set a Google Play or App Store URL in Marketing Settings first, or nothing will be sent.'
                                  )
                              ),
                              confirmText: 'Send announcement',
                              confirmIcon: 'fa-bullhorn',
                              iconClass: @js($isLaunched ? 'fa-bullhorn' : 'fa-triangle-exclamation')
                          })">
                        @csrf
                        <button type="submit"
                                class="px-3 py-2 bg-primary-600/90 hover:bg-primary-600 border border-primary-400/30 rounded-lg text-xs text-white font-medium">
                            <i class="fas fa-bullhorn mr-1"></i> Announce launch now
                            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-white/15 text-[10px]">{{ number_format($totals['pending']) }}</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @unless($isLaunched)
            <div class="mb-4 px-3 py-2 bg-amber-500/10 border border-amber-400/30 text-amber-200 rounded-lg text-xs flex items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <span>No store URL is set yet, so the announcement can't be sent. Add a Google Play or App Store URL in <span class="font-medium">Marketing Settings</span> to enable "Announce launch now".</span>
            </div>
        @endunless

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                <div class="text-xs text-white/50">All signups</div>
                <div class="text-base font-semibold text-white">{{ number_format($totals['all']) }}</div>
            </div>
            <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                <div class="text-xs text-white/50"><i class="fab fa-google-play mr-1 text-[10px]"></i>Google Play interest</div>
                <div class="text-base font-semibold text-white">{{ number_format($totals['play']) }}</div>
            </div>
            <div class="px-3 py-2 bg-white/[0.03] border border-white/5 rounded-lg">
                <div class="text-xs text-white/50"><i class="fab fa-apple mr-1 text-[10px]"></i>App Store interest</div>
                <div class="text-base font-semibold text-white">{{ number_format($totals['app']) }}</div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">
                {{ session('error') }}
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
                        <th class="py-2 pr-3">Store</th>
                        <th class="py-2 pr-3">Signed up</th>
                        <th class="py-2 pr-3">Notified</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($signups as $s)
                        <tr class="text-white/80">
                            <td class="py-2 pr-3 font-mono text-xs text-white">{{ $s->email }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                @if($s->store === 'play')
                                    <span class="inline-flex items-center gap-1"><i class="fab fa-google-play text-[10px]"></i> Google Play</span>
                                @elseif($s->store === 'app')
                                    <span class="inline-flex items-center gap-1"><i class="fab fa-apple text-[11px]"></i> App Store</span>
                                @else
 -
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ optional($s->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="py-2 pr-3 text-xs">
                                @if($s->notified_at)
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-200">{{ $s->notified_at->format('Y-m-d') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50">waiting</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">
                                <form method="POST" action="{{ route('admin.app-launch.destroy', $s) }}"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this signup?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-300 hover:text-red-200"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-white/40 text-sm">No signups yet, the form lives in the mobile-app coming-soon modal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $signups->links() }}</div>
    </div>
</div>
@endsection
