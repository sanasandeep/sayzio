@auth
    @php
        // Broken cloud connections owned by the signed-in user. We deliberately
        // scope to this user (not the workspace) because reconnecting requires
        // the original OAuth identity — only the connection owner can act.
        // Limit to a handful to keep the layout query bounded; if a creator
        // somehow has more than 5 broken at once they'll see the top 5.
        $__brokenCloudConnections = \App\Modules\User\Models\CloudConnection::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', auth()->id())
            ->whereNotNull('last_error')
            ->where(function ($q) {
                $q->whereNull('banner_dismissed_at')
                  ->orWhereColumn('last_error_at', '>', 'banner_dismissed_at')
                  ->orWhereNull('last_error_at');
            })
            ->orderByDesc('last_error_at')
            ->limit(5)
            ->get();
    @endphp
    @foreach($__brokenCloudConnections as $__bc)
        @php
            $__label = \App\Modules\User\Models\CloudProviderApp::PROVIDER_LABELS[$__bc->provider] ?? $__bc->provider;
            $__icon  = \App\Modules\User\Models\CloudProviderApp::PROVIDER_ICONS[$__bc->provider] ?? 'fas fa-cloud';
            $__acct  = $__bc->account_label ?: ($__bc->account_email ?: '');
            $__acctSuffix = $__acct !== '' ? ' (' . $__acct . ')' : '';
        @endphp
        <div class="mb-4 p-3.5 rounded-xl text-amber-300 text-xs font-medium flex items-center gap-2.5"
             style="border: 1px solid rgba(245,158,11,0.25); background: rgba(245,158,11,0.08);">
            <i class="{{ $__icon }}"></i>
            <span class="flex-1">
                Your <strong>{{ $__label }}</strong> connection{{ $__acctSuffix }} stopped working. Reconnect to keep using it in the file picker.
            </span>
            <a href="{{ route('user.cloud-files.connections') }}"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold"
               style="border: 1px solid rgba(245,158,11,0.4); background: rgba(245,158,11,0.10); color: #fcd34d;">
                <i class="fas fa-rotate text-[9px]"></i> Reconnect
            </a>
            <form action="{{ route('user.cloud-files.connections.dismiss-banner', $__bc) }}" method="POST" class="inline-flex">
                @csrf
                <button type="submit"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full text-amber-300/70 hover:text-amber-200 hover:bg-amber-500/10 transition-colors"
                        title="Dismiss"
                        aria-label="Dismiss this warning">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            </form>
        </div>
    @endforeach
@endauth
