@extends('user.layouts.app')
@section('title', 'Marketing Projects')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'Marketing projects',
        'subtitle' => 'Save a named project once — its offer, audience, budget and brand pre-fill every new strategy you build for it.',
        'balance'  => $balance,
    ])

    <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
        <a href="{{ route('user.ai.marketing-strategist.index') }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-arrow-left mr-1"></i> All strategies
        </a>
        <a href="{{ route('user.ai.marketing-strategist.projects.create') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
            <i class="fas fa-plus"></i> New project
        </a>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/[0.08] text-emerald-200 text-sm px-4 py-3 mb-4"><i class="fas fa-check-circle mr-1.5"></i>{{ session('status') }}</div>
    @endif

    @if($profiles->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-folder-open text-3xl text-indigo-300/70"></i>
            <h2 class="text-lg font-semibold text-white mt-4">No projects yet</h2>
            <p class="text-sm text-white/50 mt-1 max-w-md mx-auto">
                Create a project to store the business, offer, audience and brand behind your marketing —
                then reuse it every time you build a strategy.
            </p>
            <a href="{{ route('user.ai.marketing-strategist.projects.create') }}"
               class="inline-block mt-5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Create your first project
            </a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($profiles as $profile)
                <li class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-white font-semibold truncate">{{ $profile->displayName() }}</h3>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1 text-[11px] text-white/45">
                                @if($profile->business_name)<span><i class="fas fa-building mr-1"></i>{{ $profile->business_name }}</span>@endif
                                @if($profile->industry)<span><i class="fas fa-tag mr-1"></i>{{ $profile->industry }}</span>@endif
                                @if($profile->budget)<span><i class="fas fa-coins mr-1"></i>{{ $profile->budget }}{{ $profile->currency ? ' '.$profile->currency : '' }}</span>@endif
                            </div>
                            @if($profile->main_offer)
                                <p class="text-sm text-white/50 mt-2 line-clamp-2">{{ $profile->main_offer }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('user.ai.marketing-strategist.projects.edit', $profile->id) }}"
                               class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                                <i class="fas fa-pen mr-1"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('user.ai.marketing-strategist.projects.destroy', $profile->id) }}"
                                  onsubmit="return confirm('Delete this project? Existing strategies keep their saved copy.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-500/10 text-red-200 hover:bg-red-500/20 text-xs">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
