@extends('user.layouts.app')
@section('title', 'Delivery Projects')
@section('content')
@include('user.partials._plan_lock', ['feature' => 'tasks', 'kind' => 'flag', 'label' => 'Delivery Projects'])
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="hero-title">Delivery Projects</h1>
            <p class="hero-subtitle">Turn a finalized sale into a shared project with tasks and a timeline.</p>
        </div>
        <a href="{{ route('user.delivery-projects.create') }}"
           class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
           style="background: linear-gradient(135deg,#3d6bff,#90acff);">
            <i class="fas fa-plus mr-1"></i> New Project
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm" style="background: rgba(34,197,94,.12); color:#16a34a;">{{ session('success') }}</div>
    @endif

    @if($projects->isEmpty())
        <div class="glass-card rounded-2xl p-12 text-center" style="border:1px dashed var(--border-strong);">
            <i class="fas fa-diagram-project text-4xl mb-3 opacity-50"></i>
            <p style="color: var(--text-secondary);">No delivery projects yet.</p>
            <p class="text-sm mt-1" style="color: var(--text-tertiary);">Create one from a paid invoice, order, or form submission, or start a blank project.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($projects as $project)
                @php
                    $overall = $project->tasks_count > 0 ? (int) round(($project->done_tasks_count / $project->tasks_count) * 100) : 0;
                    $needsReply = $project->unansweredClientCount();
                @endphp
                <a href="{{ route('user.delivery-projects.show', $project) }}"
                   class="glass-card rounded-2xl p-5 block hover:shadow-lg transition" style="border:1px solid var(--border);">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-semibold" style="color: var(--text-primary);">{{ $project->title }}</h3>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($needsReply > 0)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold inline-flex items-center gap-1"
                                      style="background: rgba(239,68,68,.12); color:#ef4444;"
                                      title="{{ $needsReply }} client {{ $needsReply === 1 ? 'question' : 'questions' }} awaiting a reply">
                                    <i class="fas fa-reply"></i> {{ $needsReply }}
                                </span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: var(--surface-2); color: var(--text-secondary);">{{ $project->statusLabel() }}</span>
                        </div>
                    </div>
                    @if($project->client_name)
                        <p class="text-xs mb-3" style="color: var(--text-tertiary);"><i class="far fa-user mr-1"></i>{{ $project->client_name }}</p>
                    @endif
                    <div class="h-2 rounded-full overflow-hidden mb-1" style="background: var(--surface-2);">
                        <div class="h-full rounded-full" style="width: {{ $overall }}%; background: linear-gradient(90deg,#3d6bff,#90acff);"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs" style="color: var(--text-tertiary);">
                        <span>{{ $project->done_tasks_count }}/{{ $project->tasks_count }} tasks</span>
                        <span>{{ $overall }}%</span>
                    </div>
                    @if($project->warranty_expires_at)
                        <div class="mt-2 text-xs {{ $project->warrantyExpired() ? 'text-red-500' : '' }}" style="color: {{ $project->warrantyExpired() ? '#ef4444' : 'var(--text-tertiary)' }};">
                            <i class="fas fa-shield-halved mr-1"></i>Warranty {{ $project->warrantyExpired() ? 'expired' : 'until' }} {{ $project->warranty_expires_at->format('M j, Y') }}
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
