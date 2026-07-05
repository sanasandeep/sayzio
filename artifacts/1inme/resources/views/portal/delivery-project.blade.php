@extends('portal.layout')
@section('title', $project->title)
@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold">{{ $project->title }}</h1>
        <p class="text-sm text-slate-500">Read-only project view</p>
    </div>
    <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $project->statusLabel() }}</span>
</div>

@if($project->description)
    <p class="text-sm text-slate-600 mb-4">{{ $project->description }}</p>
@endif

@include('delivery-projects._readonly', ['project' => $project])

{{-- Ask the team a question / comment thread --}}
<div class="dp-card mt-4" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
    <h3 class="dp-card-title" style="font-size:14px;font-weight:700;margin:0 0 4px;color:#0f172a;">Questions &amp; comments</h3>
    <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Ask the team anything about your project — they’ll be notified.</p>

    @if(session('success'))
        <div style="margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#dcfce7;color:#16a34a;font-size:13px;">{{ session('success') }}</div>
    @endif

    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;">
        @forelse($project->comments as $comment)
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;background:{{ $comment->isTeam() ? '#eff6ff' : '#f8fafc' }};">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-size:12px;font-weight:600;color:{{ $comment->isTeam() ? '#3d6bff' : '#0f172a' }};">
                        {{ $comment->displayName() }} · {{ $comment->isTeam() ? 'Team' : 'You' }}
                    </span>
                    <span style="font-size:11px;color:#94a3b8;">{{ optional($comment->created_at)->diffForHumans() }}</span>
                </div>
                <div style="font-size:13px;color:#334155;white-space:pre-line;">{{ $comment->body }}</div>
            </div>
        @empty
            <p style="font-size:13px;color:#94a3b8;">No messages yet.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('portal.delivery-project.comment', $project->id) }}" style="display:flex;gap:8px;">
        @csrf
        <input name="body" required maxlength="2000" placeholder="Type your question…"
               style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;font-size:13px;color:#0f172a;">
        <button style="border:0;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;color:#fff;background:#3d6bff;cursor:pointer;">Send</button>
    </form>
</div>
@endsection
