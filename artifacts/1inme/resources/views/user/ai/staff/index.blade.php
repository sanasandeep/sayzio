@extends('user.layouts.app')
@section('title', 'AI Staff')

@section('content')
@include('user.partials.ai-workspace-nav')
<div class="w-full">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'AI Staff',
        'subtitle' => 'Hire internal AI Staff for sales, support, marketing and operations. Review their suggestions before applying changes.',
        'balance'  => $balance,
    ])

    @if(\Illuminate\Support\Facades\Route::has('user.ai.staff.reports'))
    <a href="{{ route('user.ai.staff.reports') }}" class="btn-secondary inline-flex mb-5"><i class="fas fa-chart-line mr-2"></i>Reports & activity</a>
    @endif
    @if(!$enabled)
        <div class="rounded-2xl border border-amber-400/20 bg-amber-400/5 p-4 text-amber-200/90 text-sm mb-6">
            AI features are currently disabled on this platform.
        </div>
    @endif

    <form method="POST" action="{{ route('user.ai.staff.store') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-8">
        @csrf
        <h3 class="text-white font-semibold mb-3"><i class="fas fa-user-plus text-blue-300/80 mr-1.5"></i>Hire a new staff member</h3>
        <div class="flex flex-wrap gap-2 mb-4" aria-label="Role shortcuts">
            @foreach(['sales'=>'Sales closer','support'=>'Support specialist','marketing'=>'Content strategist','projects'=>'Project coordinator','client_success'=>'Client success','scheduling'=>'Scheduling assistant'] as $role=>$name)
            <button type="button" class="rounded-xl border px-3 py-2 text-xs" onclick='const form=this.closest("form"); form.elements.name.value=@js($name); form.elements.domain.value=@js($role); form.elements.instructions.value=@js($domainDesc[$role]);'>{{ $name }}</button>
            @endforeach
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-white/50 mb-1">Name</label>
                <input type="text" name="name" required maxlength="120" placeholder="e.g. Nora"
                       class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-400/50">
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Domain</label>
                <select name="domain" required class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-400/50">
                    @foreach($domains as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-white/50 mb-1">Instructions / personality (optional)</label>
            <textarea name="instructions" maxlength="4000" rows="2" placeholder="e.g. Friendly but concise. Always mention our 7-day return policy."
                      class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-400/50"></textarea>
        </div>
        @include('user.ai.staff.connections', ['staff'=>null])
        <button type="submit" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
            <i class="fas fa-wand-magic-sparkles"></i> Hire
        </button>
    </form>

    @if($staff->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-people-group text-3xl text-blue-300/70"></i>
            <h2 class="text-lg font-semibold text-white mt-4">No AI staff yet</h2>
            <p class="text-sm text-white/50 mt-1 max-w-md mx-auto">Hire your first staff member above to start delegating billing chases, contact follow-ups, and account questions.</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($staff as $s)
                <li>
                    <a href="{{ route('user.ai.staff.show', $s) }}"
                       class="block rounded-2xl border border-white/10 bg-white/[0.03] p-4 hover:bg-white/[0.06] transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-white font-semibold truncate">{{ $s->name }}</h3>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-200">{{ $s->domainLabel() }}</span>
                                    @if($s->is_disabled)
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/10 text-white/50">Disabled</span>
                                    @endif
                                </div>
                                <p class="text-sm text-white/50 mt-1 line-clamp-2">{{ $domainDesc[$s->domain] ?? '' }}</p>
                                @if($s->last_used_at)
                                    <p class="text-[11px] text-white/30 mt-1">Last used {{ $s->last_used_at->diffForHumans() }}</p>
                                @endif
                            </div>
                            <i class="fas fa-chevron-right text-white/20 mt-1"></i>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if($suggestions->isNotEmpty())
        <h3 class="text-white font-semibold mt-10 mb-3"><i class="fas fa-lightbulb text-amber-300/80 mr-1.5"></i>Recent suggestions</h3>
        <ul class="space-y-2">
            @foreach($suggestions as $sug)
                <li class="rounded-xl border border-white/10 bg-white/[0.02] p-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-white/80 truncate">{{ $sug->title }}</p>
                        <p class="text-[11px] text-white/40">{{ $sug->aiStaff->name ?? 'AI Staff' }} &middot; {{ ucfirst($sug->status) }} &middot; {{ $sug->created_at->diffForHumans() }}</p>
                    </div>
                    @if($sug->aiStaff)
                        <a href="{{ route('user.ai.staff.show', $sug->aiStaff) }}" class="text-xs text-blue-300 hover:text-blue-200 shrink-0">View →</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
