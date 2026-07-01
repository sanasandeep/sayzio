@extends('user.layouts.settings')
@section('title', 'Custom Domains')

@section('settings-content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canEdit = $__can('settings.edit');
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Custom Domains</h1>
            <p class="text-xs text-white/40 mt-1">Use your own domain for short links. Add a CNAME record at your DNS provider, then click Verify.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl text-red-400 text-xs bg-red-500/5 border border-red-500/15">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Add new --}}
    @if($__canEdit)
    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-base font-semibold text-white mb-4">Add Domain</h2>
        <form method="POST" action="{{ route('user.domains.store') }}" class="flex gap-3">
            @csrf
            <input type="text" name="domain" placeholder="links.yourbrand.com" required
                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Add</button>
        </form>
        <p class="text-[11px] text-white/40 mt-2">After adding, you'll get the exact CNAME record (name + target + TTL) to paste into your DNS provider — then click Verify here.</p>
    </div>
    @else
    <div class="rounded-2xl p-4 mb-6 flex items-start gap-3" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); color:#b45309;">
        <i class="fas fa-lock mt-0.5"></i>
        <div class="text-xs">
            <div class="font-semibold mb-0.5">View-only access</div>
            Your role doesn't allow adding or removing custom domains. Ask a workspace admin to add a domain for you.
        </div>
    </div>
    @endif

    {{-- My domains --}}
    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-base font-semibold text-white mb-4">My Domains ({{ $myDomains->count() }})</h2>
        @forelse($myDomains as $d)
            <div class="flex items-center justify-between gap-3 py-3 border-t border-white/5 first:border-t-0">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-mono text-white flex items-center gap-2 flex-wrap">
                        <span>{{ $d->domain }}</span>
                        @php
                            $__expectedCname = $d->cname_target ?: parse_url(config('app.url'), PHP_URL_HOST);
                            $__status = $d->dns_status;
                            if (!$d->is_verified && $__status === \App\Modules\User\Models\Domain::DNS_STATUS_UNVERIFIED) {
                                // Auto-unverified after grace — distinct from a brand-new
                                // domain that has never been verified.
                                $__badge = ['label' => 'auto-unverified', 'cls' => 'bg-red-500/15 text-red-300 border-red-400/30'];
                            } elseif (!$d->is_verified) {
                                $__badge = ['label' => 'unverified', 'cls' => 'bg-white/10 text-white/60 border-white/15'];
                            } elseif ($__status === \App\Modules\User\Models\Domain::DNS_STATUS_DRIFTING) {
                                $__badge = ['label' => 'drifting', 'cls' => 'bg-amber-500/15 text-amber-300 border-amber-400/30'];
                            } else {
                                $__badge = ['label' => 'healthy', 'cls' => 'bg-emerald-500/15 text-emerald-300 border-emerald-400/30'];
                            }
                        @endphp
                        <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $__badge['cls'] }}">{{ $__badge['label'] }}</span>
                    </div>
                    <div class="text-[11px] text-white/40 mt-1 space-y-0.5">
                        @if($d->is_verified && $__status !== \App\Modules\User\Models\Domain::DNS_STATUS_DRIFTING)
                            <div><span class="text-emerald-400">verified</span> · serving short links
                                @if($d->dns_last_checked_at) · <span class="text-white/30">DNS checked {{ $d->dns_last_checked_at->diffForHumans() }}</span>@endif
                            </div>
                        @elseif($d->is_verified && $__status === \App\Modules\User\Models\Domain::DNS_STATUS_DRIFTING)
                            <div class="text-amber-300">
                                DNS for this domain stopped pointing at Sayzio
                                @if($d->dns_drift_started_at) ({{ $d->dns_drift_started_at->diffForHumans() }})@endif.
                                Restore the CNAME below or this domain will be auto-unverified after {{ \App\Modules\Common\Services\DomainHealthChecker::graceHours() }}h.
                            </div>
                            <div class="font-mono text-white/60">
                                Type: <span class="text-blue-300">CNAME</span> ·
                                Name: <span class="text-blue-300">{{ $d->domain }}</span> ·
                                Target: <span class="text-blue-300">{{ $__expectedCname }}</span> ·
                                TTL: <span class="text-blue-300">300</span>
                            </div>
                            @if($d->dns_last_target)
                                <div class="text-white/30">Currently resolving to: <span class="font-mono">{{ $d->dns_last_target }}</span></div>
                            @endif
                        @else
                            @if($__status === \App\Modules\User\Models\Domain::DNS_STATUS_UNVERIFIED)
                                <div class="text-red-300">Auto-unverified after the grace window — fix DNS, then re-verify. The host is still locked to your account so no one else can claim it.</div>
                            @else
                                <div><span class="text-amber-400">unverified</span> — add this DNS record at your registrar:</div>
                            @endif
                            <div class="font-mono text-white/60">
                                Type: <span class="text-blue-300">CNAME</span> ·
                                Name: <span class="text-blue-300">{{ $d->domain }}</span> ·
                                Target: <span class="text-blue-300">{{ $d->cname_target ?: parse_url(config('app.url'), PHP_URL_HOST) }}</span> ·
                                TTL: <span class="text-blue-300">300</span> (or Auto)
                            </div>
                            <div class="text-white/30">DNS changes can take up to 24 hours to propagate. If verify fails, wait a few minutes and try again.</div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if(!$d->is_verified && $__canEdit)
                        <form method="POST" action="{{ route('user.domains.verify', $d) }}">@csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs bg-blue-600 hover:bg-blue-700 text-white">Verify now</button>
                        </form>
                    @elseif(!$d->is_verified)
                        <span class="px-3 py-1.5 rounded-lg text-xs cursor-not-allowed opacity-60 bg-blue-600/40 text-white" title="Your role doesn't allow verifying domains — ask a workspace admin"><i class="fas fa-lock mr-1"></i>Verify</span>
                    @endif
                    @if($__canEdit)
                    <form method="POST" action="{{ route('user.domains.destroy', $d) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove {{ $d->domain }}?', message: 'Existing links bound to it will lose their custom host.', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs bg-red-600/20 text-red-400 hover:bg-red-600/30">Remove</button>
                    </form>
                    @else
                    <span class="px-3 py-1.5 rounded-lg text-xs cursor-not-allowed opacity-60 bg-white/5 text-white/40" title="Your role doesn't allow removing domains — ask a workspace admin"><i class="fas fa-lock mr-1"></i>Remove</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-xs text-white/40">No custom domains yet. Add one above.</p>
        @endforelse
    </div>

    {{-- Global domains available for the user's plan --}}
    @if($globalDomains->isNotEmpty())
    <div class="glass rounded-2xl p-6">
        <h2 class="text-base font-semibold text-white mb-1">Included with your plan</h2>
        <p class="text-xs text-white/40 mb-4">These shared domains are managed by us and ready to use — pick one when creating or editing a link.</p>
        <ul class="text-sm text-white/80 space-y-1.5">
            @foreach($globalDomains as $d)
                <li class="font-mono">· {{ $d->domain }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection
