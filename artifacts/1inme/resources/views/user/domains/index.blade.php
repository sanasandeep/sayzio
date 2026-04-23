@extends('user.layouts.app')
@section('title', 'Custom Domains')

@section('content')
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
                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Add</button>
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
                    <div class="text-sm font-mono text-white">{{ $d->domain }}</div>
                    <div class="text-[11px] text-white/40 mt-0.5 space-y-0.5">
                        @if($d->is_verified)
                            <div><span class="text-emerald-400">verified</span> · serving short links</div>
                        @else
                            <div><span class="text-amber-400">unverified</span> — add this DNS record at your registrar:</div>
                            <div class="font-mono text-white/60">
                                Type: <span class="text-violet-300">CNAME</span> ·
                                Name: <span class="text-violet-300">{{ $d->domain }}</span> ·
                                Target: <span class="text-violet-300">{{ $d->cname_target ?: parse_url(config('app.url'), PHP_URL_HOST) }}</span> ·
                                TTL: <span class="text-violet-300">300</span> (or Auto)
                            </div>
                            <div class="text-white/30">DNS changes can take up to 24 hours to propagate. If verify fails, wait a few minutes and try again.</div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if(!$d->is_verified && $__canEdit)
                        <form method="POST" action="{{ route('user.domains.verify', $d) }}">@csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs bg-violet-600 hover:bg-violet-700 text-white">Verify now</button>
                        </form>
                    @elseif(!$d->is_verified)
                        <span class="px-3 py-1.5 rounded-lg text-xs cursor-not-allowed opacity-60 bg-violet-600/40 text-white" title="Your role doesn't allow verifying domains — ask a workspace admin"><i class="fas fa-lock mr-1"></i>Verify</span>
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
