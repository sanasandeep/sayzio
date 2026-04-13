@extends('user.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ $user->name }}</h1>
    <p class="text-gray-500 mt-1">Manage your links and pages</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Plan</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ $user->plan->name ?? 'Free' }}</p>
            </div>
            <div class="w-9 h-9 bg-sky-50 rounded-lg flex items-center justify-center">
                <i class="fas fa-crown text-sky-600 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Links</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ $totalLinks }}</p>
            </div>
            <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center">
                <i class="fas fa-link text-green-600 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Clicks</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center">
                <i class="fas fa-mouse-pointer text-purple-600 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Today</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format($clicksToday) }}</p>
            </div>
            <div class="w-9 h-9 bg-orange-50 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-line text-orange-600 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Projects</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ $totalProjects }}</p>
            </div>
            <div class="w-9 h-9 bg-indigo-50 rounded-lg flex items-center justify-center">
                <i class="fas fa-folder text-indigo-600 text-sm"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Recent Links</h2>
                <a href="{{ route('user.links.create') }}" class="text-sm text-primary-600 hover:text-primary-700 font-medium">+ New Link</a>
            </div>

            @if($recentLinks->isEmpty())
            <div class="p-8 text-center">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-link text-gray-400"></i>
                </div>
                <p class="text-gray-500 text-sm">No links yet. Create your first one!</p>
                <a href="{{ route('user.links.create') }}" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium mt-3">
                    <i class="fas fa-plus"></i> Create Link
                </a>
            </div>
            @else
            <div class="divide-y divide-gray-100">
                @foreach($recentLinks as $link)
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.links.show', $link) }}" class="font-medium text-gray-900 hover:text-primary-600 truncate">{{ $link->title ?: $link->alias }}</a>
                                <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded uppercase">{{ $link->type }}</span>
                            </div>
                            <div class="text-sm text-primary-600 truncate mt-0.5">{{ $link->getShortUrl() }}</div>
                        </div>
                        <div class="text-right ml-4">
                            <div class="font-semibold text-gray-900">{{ number_format($link->total_clicks) }}</div>
                            <div class="text-xs text-gray-500">clicks</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="p-4 border-t border-gray-100 text-center">
                <a href="{{ route('user.links.index') }}" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all links</a>
            </div>
            @endif
        </div>
    </div>

    <div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Quick Actions</h2>
            <div class="space-y-2">
                <a href="{{ route('user.links.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center"><i class="fas fa-link text-blue-600 text-xs"></i></div>
                    <span class="text-gray-700">Shorten a URL</span>
                </a>
                <a href="{{ route('user.projects.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                    <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center"><i class="fas fa-folder-plus text-indigo-600 text-xs"></i></div>
                    <span class="text-gray-700">Create Project</span>
                </a>
                <a href="{{ route('user.pixels.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                    <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center"><i class="fas fa-bullseye text-purple-600 text-xs"></i></div>
                    <span class="text-gray-700">Add Tracking Pixel</span>
                </a>
                <a href="{{ route('user.profile.edit') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center"><i class="fas fa-cog text-gray-600 text-xs"></i></div>
                    <span class="text-gray-700">Account Settings</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
