@extends('user.layouts.app')
@section('title', 'Message History')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('user.subscribers.compose') }}" class="p-2 rounded-xl glass transition hover:bg-white/5" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Message History</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-muted);">All messages sent to your leads</p>
        </div>
    </div>

    @if($messages->count())
    <div class="space-y-3">
        @foreach($messages as $msg)
        <div class="glass rounded-2xl p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: {{ $msg->channel === 'email' ? 'rgba(124,58,237,0.15)' : 'rgba(37,211,102,0.15)' }};">
                        <i class="{{ $msg->channel === 'email' ? 'fas fa-envelope text-violet-400' : 'fab fa-whatsapp' }}" style="{{ $msg->channel !== 'email' ? 'color:#25D366' : '' }}"></i>
                    </div>
                    <div>
                        @if($msg->subject)<h3 class="font-semibold text-sm" style="color: var(--text-primary);">{{ $msg->subject }}</h3>@endif
                        <p class="text-xs" style="color: var(--text-muted);">{{ $msg->sent_at?->format('M d, Y \a\t g:i A') ?? $msg->created_at->format('M d, Y \a\t g:i A') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-xs" style="color: var(--text-muted);">
                    <span><i class="fas fa-users mr-1"></i>{{ $msg->recipients_count }}</span>
                    <span class="text-green-400"><i class="fas fa-check mr-1"></i>{{ $msg->sent_count }}</span>
                    @if($msg->failed_count > 0)
                    <span class="text-red-400"><i class="fas fa-times mr-1"></i>{{ $msg->failed_count }}</span>
                    @endif
                </div>
            </div>
            <div class="text-sm leading-relaxed rounded-xl p-3" style="color: var(--text-secondary); background: var(--bg-input);">
                {!! nl2br(e(Str::limit($msg->body, 300))) !!}
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
    @else
    <div class="glass rounded-2xl p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background: linear-gradient(135deg, rgba(124,58,237,0.2), rgba(139,92,246,0.1));">
            <i class="fas fa-paper-plane text-2xl text-violet-400"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No messages yet</h3>
        <p class="text-sm" style="color: var(--text-muted);">Messages you send to leads will appear here.</p>
    </div>
    @endif
</div>
@endsection
