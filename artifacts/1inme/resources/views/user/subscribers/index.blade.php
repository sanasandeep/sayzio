@extends('user.layouts.app')
@section('title', 'Subscribers')

@section('content')
<div class="max-w-7xl mx-auto" x-data="{ deleteId: null }">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Subscribers</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Manage your email & WhatsApp subscribers</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.subscribers.compose') }}" class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                <i class="fas fa-paper-plane mr-1.5"></i>Compose
            </a>
            <a href="{{ route('user.subscribers.settings') }}" class="px-4 py-2 rounded-xl text-sm font-medium transition-all hover:-translate-y-0.5 glass" style="color: var(--text-secondary);">
                <i class="fas fa-cog mr-1.5"></i>Settings
            </a>
            <a href="{{ route('user.subscribers.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="px-4 py-2 rounded-xl text-sm font-medium transition-all hover:-translate-y-0.5 glass" style="color: var(--text-secondary);">
                <i class="fas fa-download mr-1.5"></i>Export
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Total</div>
        </div>
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-green-400">{{ number_format($stats['active']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Active</div>
        </div>
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-violet-400">{{ number_format($stats['email']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Email</div>
        </div>
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: #25D366;">{{ number_format($stats['whatsapp_channel']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">WA Channel</div>
        </div>
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: #25D366;">{{ number_format($stats['whatsapp_number']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">WA Number</div>
        </div>
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
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Biolink</label>
                <select name="link_id" class="px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <option value="">All Links</option>
                    @foreach($links as $l)
                    <option value="{{ $l->id }}" {{ request('link_id') == $l->id ? 'selected' : '' }}>{{ $l->alias }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium text-white" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">Filter</button>
            @if(request()->hasAny(['search','type','status','link_id']))
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
                        <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--text-muted);">Subscriber</th>
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
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium" style="background: rgba(124,58,237,0.15); color: #a78bfa;">
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
                        <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">{{ $sub->source ?? '—' }}</td>
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
                                <form method="POST" action="{{ route('user.subscribers.toggle', $sub) }}">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg transition hover:bg-white/5" title="{{ $sub->status === 'active' ? 'Unsubscribe' : 'Reactivate' }}" style="color: var(--text-muted);">
                                        <i class="fas {{ $sub->status === 'active' ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.subscribers.destroy', $sub) }}" onsubmit="return confirm('Remove this subscriber?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg transition hover:bg-red-500/10" style="color: var(--text-muted);" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
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
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background: linear-gradient(135deg, rgba(124,58,237,0.2), rgba(139,92,246,0.1));">
            <i class="fas fa-users text-2xl text-violet-400"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No subscribers yet</h3>
        <p class="text-sm mb-4" style="color: var(--text-muted);">Add Email Subscribe, WhatsApp Channel, or WhatsApp Number blocks to your biolinks to start collecting subscribers.</p>
        <a href="{{ route('user.links.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-white" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
            <i class="fas fa-link"></i>Go to Links
        </a>
    </div>
    @endif
</div>
@endsection
