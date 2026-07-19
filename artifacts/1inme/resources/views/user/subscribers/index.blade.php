@extends('user.layouts.app')
@section('title', 'Subscribers')

@push('styles')
    {{-- Reuse the exact bento command-center look from the Dashboard. --}}
    @include('user.partials.bento-styles')
@endpush

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canCreate = $__can('inbox.create');
    $__canEdit = $__can('inbox.edit');
    $__canDelete = $__can('inbox.delete');
    $__wa = (int) $stats['whatsapp_channel'] + (int) $stats['whatsapp_number'];
    $__tiles = [
        ['label' => 'Total',      'value' => number_format($stats['total']),            'icon' => 'fa-users',        'accent' => 'linear-gradient(90deg, #5c83ff, #90acff)', 'glow' => 'rgba(61,107,255,0.16)', 'iconBg' => 'rgba(61,107,255,0.12)', 'iconBd' => 'rgba(61,107,255,0.2)', 'iconColor' => '#90acff'],
        ['label' => 'Active',     'value' => number_format($stats['active']),           'icon' => 'fa-circle-check',  'accent' => 'linear-gradient(90deg, #10b981, #34d399)', 'glow' => 'rgba(16,185,129,0.18)', 'iconBg' => 'rgba(16,185,129,0.12)', 'iconBd' => 'rgba(16,185,129,0.2)', 'iconColor' => '#34d399'],
        ['label' => 'Email',      'value' => number_format($stats['email']),            'icon' => 'fa-envelope',      'accent' => 'linear-gradient(90deg, #0ea5e9, #38bdf8)', 'glow' => 'rgba(14,165,233,0.18)', 'iconBg' => 'rgba(14,165,233,0.12)', 'iconBd' => 'rgba(14,165,233,0.2)', 'iconColor' => '#38bdf8'],
        ['label' => 'WA Channel', 'value' => number_format($stats['whatsapp_channel']), 'icon' => 'fa-whatsapp fab', 'accent' => 'linear-gradient(90deg, #16a34a, #25D366)',  'glow' => 'rgba(37,211,102,0.18)', 'iconBg' => 'rgba(37,211,102,0.12)', 'iconBd' => 'rgba(37,211,102,0.2)', 'iconColor' => '#25D366'],
        ['label' => 'WA Number',  'value' => number_format($stats['whatsapp_number']),  'icon' => 'fa-whatsapp fab', 'accent' => 'linear-gradient(90deg, #0d9488, #2dd4bf)', 'glow' => 'rgba(45,212,191,0.18)', 'iconBg' => 'rgba(45,212,191,0.12)', 'iconBd' => 'rgba(45,212,191,0.2)', 'iconColor' => '#2dd4bf'],
    ];
@endphp
<div class="max-w-7xl mx-auto bento-stage" x-data="{ deleteId: null }">

    {{-- ===================== LIVE-PULSE HERO ===================== --}}
    <div class="bento-hero">
        <div class="hero-grid">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="hero-chip"><i class="fas fa-users"></i> {{ number_format($stats['total']) }} leads</span>
                    <span class="hero-chip"><i class="fas fa-circle text-emerald-400" style="font-size:6px;"></i> {{ number_format($stats['active']) }} active</span>
                    @if($__wa)<span class="hero-chip"><i class="fab fa-whatsapp"></i> {{ number_format($__wa) }} WhatsApp</span>@endif
                </div>
                <h1 class="hero-title gradient-text truncate" style="font-size: clamp(1.5rem, 3.2vw, 2.1rem);">Subscribers</h1>
                <p class="hero-subtitle">Manage your email &amp; WhatsApp subscribers.</p>
                <div class="flex items-center gap-2 flex-wrap mt-4">
                    @if($__canCreate)
                    <a href="{{ route('user.subscribers.compose') }}" class="btn-primary text-xs py-2">
                        <i class="fas fa-paper-plane text-[10px]"></i> Compose
                    </a>
                    @else
                    <span class="btn-primary text-xs py-2 cursor-not-allowed opacity-60" title="Your role doesn't allow composing campaigns, ask a workspace admin">
                        <i class="fas fa-lock text-[10px]"></i> Compose
                    </span>
                    @endif
                    @if($__canEdit)
                    <a href="{{ route('user.subscribers.settings') }}" class="btn-ghost text-xs py-2">
                        <i class="fas fa-cog text-[10px]"></i> Settings
                    </a>
                    @else
                    <span class="btn-ghost text-xs py-2 cursor-not-allowed opacity-60" title="Your role doesn't allow editing leads settings, ask a workspace admin">
                        <i class="fas fa-lock text-[10px]"></i> Settings
                    </span>
                    @endif
                    <a href="{{ route('user.subscribers.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn-ghost text-xs py-2">
                        <i class="fas fa-download text-[10px]"></i> Export
                    </a>
                </div>
            </div>

            {{-- Live pulse: active leads --}}
            <div class="flex items-center gap-4">
                <div class="pulse-orb">
                    <span class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['active']) }}</span>
                    <span class="text-[9px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">active</span>
                </div>
                <div>
                    <span class="live-dot"><span class="dot"></span> Live</span>
                    <p class="text-sm font-semibold mt-1.5" style="color: var(--text-primary);">Active leads</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                        out of <strong style="color: var(--text-secondary);">{{ number_format($stats['total']) }}</strong> total
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== METRIC BENTO ===================== --}}
    <div class="bento mb-6">
        @foreach($__tiles as $t)
            <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: {{ $t['accent'] }}; --tile-glow: {{ $t['glow'] }};">
                <span class="tile-orb"></span>
                <div class="flex items-center justify-between">
                    <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">{{ $t['label'] }}</p>
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: {{ $t['iconBg'] }}; border: 1px solid {{ $t['iconBd'] }};">
                        <i class="{{ str_contains($t['icon'], 'fab') ? 'fab' : 'fas' }} {{ str_replace(' fab', '', $t['icon']) }} text-xs" style="color: {{ $t['iconColor'] }};"></i>
                    </div>
                </div>
                <p class="text-2xl font-extrabold mt-2" style="color: var(--text-primary);">{{ $t['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="glass rounded-2xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Email, name, phone..." class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
            </div>
            <div>
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Type</label>
                <select name="type" class="px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">All Types</option>
                    <option value="email" {{ request('type') === 'email' ? 'selected' : '' }}>Email</option>
                    <option value="whatsapp_channel" {{ request('type') === 'whatsapp_channel' ? 'selected' : '' }}>WhatsApp Channel</option>
                    <option value="whatsapp_number" {{ request('type') === 'whatsapp_number' ? 'selected' : '' }}>WhatsApp Number</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Status</label>
                <select name="status" class="px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="unsubscribed" {{ request('status') === 'unsubscribed' ? 'selected' : '' }}>Unsubscribed</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Link in Bio</label>
                <select name="link_id" class="px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">All Links</option>
                    @foreach($links as $l)
                    <option value="{{ $l->id }}" {{ request('link_id') == $l->id ? 'selected' : '' }}>{{ $l->alias }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Visitor Type</label>
                <select name="visitor_type" class="px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">All</option>
                    <option value="student" {{ request('visitor_type') === 'student' ? 'selected' : '' }}>Student</option>
                    <option value="professional" {{ request('visitor_type') === 'professional' ? 'selected' : '' }}>Professional</option>
                    <option value="business" {{ request('visitor_type') === 'business' ? 'selected' : '' }}>Business Owner</option>
                    <option value="creator" {{ request('visitor_type') === 'creator' ? 'selected' : '' }}>Creator</option>
                    <option value="other" {{ request('visitor_type') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium text-white" style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">Filter</button>
            @if(request()->hasAny(['search','type','status','link_id','visitor_type']))
            <a href="{{ route('user.subscribers.index') }}" class="px-3 py-2 rounded-xl text-sm" style="color: var(--text-muted);">Clear</a>
            @endif
        </form>
    </div>

    @if($subscribers->count())
    <div class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="enhanced-table w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-subtle);">
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Lead</th>
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Source</th>
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Date</th>
                        <th class="text-right px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);" data-no-sort>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscribers as $sub)
                    <tr style="border-bottom: 1px solid var(--border-subtle);" class="hover:bg-white/[0.02] transition">
                        <td class="px-4 py-3">
                            <div>
                                @if($sub->name)<span class="font-medium" style="color: var(--text-primary);">{{ $sub->name }}</span><br>@endif
                                @if($sub->email)<span class="text-xs" style="color: var(--text-muted);">{{ $sub->email }}</span>@endif
                                @if($sub->phone)<span class="text-xs" style="color: var(--text-muted);">{{ $sub->phone }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($sub->type === 'email')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium" style="background: rgba(61,107,255,0.15); color: #90acff;">
                                <i class="fas fa-envelope text-[10px]"></i>Email
                            </span>
                            @elseif($sub->type === 'whatsapp_channel')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium" style="background: rgba(37,211,102,0.15); color: #25D366;">
                                <i class="fab fa-whatsapp text-[10px]"></i>Channel
                            </span>
                            @elseif($sub->type === 'whatsapp_number')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium" style="background: rgba(37,211,102,0.15); color: #25D366;">
                                <i class="fab fa-whatsapp text-[10px]"></i>Number
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">
                            @if(data_get($sub->metadata, 'origin') === 'buzz')
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-xs font-medium" style="background: rgba(245,158,11,0.15); color: #f59e0b;" title="Captured by a Buzz popup">
                                <i class="fas fa-bolt text-[10px]"></i>{{ data_get($sub->metadata, 'campaign') ?: 'Buzz' }}
                            </span>
                            @else
                            {{ $sub->source ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($sub->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium" style="background: rgba(34,197,94,0.15); color: #4ade80;">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>Active
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium" style="background: rgba(239,68,68,0.1); color: #f87171;">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>Unsubscribed
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">{{ $sub->subscribed_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($__canEdit)
                                <form method="POST" action="{{ route('user.subscribers.toggle', $sub) }}">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg transition hover:bg-white/5" title="{{ $sub->status === 'active' ? 'Unsubscribe' : 'Reactivate' }}" style="color: var(--text-muted);">
                                        <i class="fas {{ $sub->status === 'active' ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                    </button>
                                </form>
                                @else
                                <span class="p-1.5 rounded-lg cursor-not-allowed opacity-60" style="color: var(--text-faint);" title="Your role doesn't allow changing lead status, ask a workspace admin">
                                    <i class="fas fa-lock text-xs"></i>
                                </span>
                                @endif
                                @if($__canDelete)
                                <form method="POST" action="{{ route('user.subscribers.destroy', $sub) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this lead?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg transition hover:bg-red-500/10" style="color: var(--text-muted);" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                                @else
                                <span class="p-1.5 rounded-lg cursor-not-allowed opacity-60" style="color: var(--text-faint);" title="Your role doesn't allow deleting leads, ask a workspace admin">
                                    <i class="fas fa-lock text-xs"></i>
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $subscribers->links() }}</div>
    @include('common.partials.enhanced-table')
    @else
    <div class="glass rounded-2xl p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background: linear-gradient(135deg, rgba(61,107,255,0.2), rgba(92,131,255,0.1));">
            <i class="fas fa-users text-2xl text-blue-400"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No leads yet</h3>
        <p class="text-sm mb-4" style="color: var(--text-muted);">Add Email Subscribe, WhatsApp Channel, or WhatsApp Number blocks to your link in bio pages to start collecting leads.</p>
        <a href="{{ route('user.links.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-white" style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">
            <i class="fas fa-link"></i>Go to Links
        </a>
    </div>
    @endif
</div>
@endsection
