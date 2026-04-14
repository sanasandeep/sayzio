@extends('user.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-2xl font-bold text-white">Welcome back, {{ $user->name }}</h1>
    <p class="text-white/40 mt-1">Here's what's happening with your links</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="glass rounded-2xl p-5 group hover:bg-white/[0.06] transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-white/30 uppercase tracking-wider font-medium">Plan</p>
                <p class="text-xl font-bold text-white mt-1.5">{{ $user->plan->name ?? 'Free' }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-crown text-purple-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5 group hover:bg-white/[0.06] transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-white/30 uppercase tracking-wider font-medium">Links</p>
                <p class="text-xl font-bold text-white mt-1.5">{{ $totalLinks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-link text-emerald-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5 group hover:bg-white/[0.06] transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-white/30 uppercase tracking-wider font-medium">Total Clicks</p>
                <p class="text-xl font-bold text-white mt-1.5">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-mouse-pointer text-blue-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5 group hover:bg-white/[0.06] transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-white/30 uppercase tracking-wider font-medium">Today</p>
                <p class="text-xl font-bold text-white mt-1.5">{{ number_format($clicksToday) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-chart-line text-amber-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5 group hover:bg-white/[0.06] transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-white/30 uppercase tracking-wider font-medium">Projects</p>
                <p class="text-xl font-bold text-white mt-1.5">{{ $totalProjects }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-folder text-indigo-400 text-sm"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="glass rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-white/5">
                <h2 class="font-semibold text-white">Recent Links</h2>
                <a href="{{ route('user.links.create') }}" class="text-sm text-purple-400 hover:text-purple-300 font-medium transition-colors">
                    <i class="fas fa-plus text-xs mr-1"></i> New Link
                </a>
            </div>

            @if($recentLinks->isEmpty())
            <div class="p-10 text-center">
                <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-link text-white/20 text-xl"></i>
                </div>
                <p class="text-white/40 text-sm mb-4">No links yet. Create your first one!</p>
                <a href="{{ route('user.links.create') }}" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-purple-500/20">
                    <i class="fas fa-plus text-xs"></i> Create Link
                </a>
            </div>
            @else
            <div class="divide-y divide-white/5">
                @foreach($recentLinks as $link)
                <a href="{{ route('user.links.show', $link) }}" class="block p-4 hover:bg-white/[0.03] transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-white truncate">{{ $link->title ?: $link->alias }}</span>
                                <span class="text-[10px] bg-white/10 text-white/50 px-1.5 py-0.5 rounded-md uppercase font-medium">{{ $link->type }}</span>
                                @if(!$link->is_active)
                                <span class="text-[10px] bg-red-500/10 text-red-400 px-1.5 py-0.5 rounded-md">inactive</span>
                                @endif
                            </div>
                            <div class="text-sm text-purple-400/70 truncate mt-0.5">{{ $link->getShortUrl() }}</div>
                        </div>
                        <div class="text-right ml-4 flex-shrink-0">
                            <div class="font-bold text-white">{{ number_format($link->total_clicks) }}</div>
                            <div class="text-[11px] text-white/30">clicks</div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            <div class="p-4 border-t border-white/5 text-center">
                <a href="{{ route('user.links.index') }}" class="text-sm text-purple-400 hover:text-purple-300 font-medium transition-colors">View all links <i class="fas fa-arrow-right text-xs ml-1"></i></a>
            </div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-4">Quick Actions</h2>
            <div class="space-y-1.5">
                <a href="{{ route('user.links.create') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] transition-colors text-sm group">
                    <div class="w-9 h-9 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fas fa-link text-purple-400 text-xs"></i>
                    </div>
                    <span class="text-white/60 group-hover:text-white/90 transition-colors">Shorten a URL</span>
                </a>
                <a href="{{ route('user.projects.create') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] transition-colors text-sm group">
                    <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fas fa-folder-plus text-indigo-400 text-xs"></i>
                    </div>
                    <span class="text-white/60 group-hover:text-white/90 transition-colors">Create Project</span>
                </a>
                <a href="{{ route('user.pixels.create') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] transition-colors text-sm group">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fas fa-bullseye text-emerald-400 text-xs"></i>
                    </div>
                    <span class="text-white/60 group-hover:text-white/90 transition-colors">Add Tracking Pixel</span>
                </a>
                <a href="{{ route('user.qrcode') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] transition-colors text-sm group">
                    <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fas fa-qrcode text-cyan-400 text-xs"></i>
                    </div>
                    <span class="text-white/60 group-hover:text-white/90 transition-colors">Generate QR Code</span>
                </a>
                <a href="{{ route('user.profile.edit') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] transition-colors text-sm group">
                    <div class="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fas fa-cog text-white/40 text-xs"></i>
                    </div>
                    <span class="text-white/60 group-hover:text-white/90 transition-colors">Account Settings</span>
                </a>
            </div>
        </div>

        <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3">Your Plan</h2>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center shadow-lg shadow-purple-500/20">
                    <i class="fas fa-gem text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-white font-semibold">{{ $user->plan->name ?? 'Free' }}</p>
                    <p class="text-xs text-white/30">{{ $user->plan_expires_at ? 'Expires ' . $user->plan_expires_at->format('M d, Y') : 'No expiration' }}</p>
                </div>
            </div>
            <div class="h-px bg-white/5 mb-3"></div>
            <p class="text-xs text-white/30">{{ $totalLinks }} / {{ $user->plan->settings['links_limit'] ?? '∞' }} links used</p>
        </div>
    </div>
</div>
@endsection
