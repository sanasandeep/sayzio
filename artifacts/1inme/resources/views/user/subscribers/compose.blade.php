@extends('user.layouts.app')
@section('title', 'Compose Message')

@section('content')
<div class="max-w-3xl mx-auto" x-data="{ channel: 'email' }">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('user.subscribers.index') }}" class="p-2 rounded-xl glass transition hover:bg-white/5" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Compose Message</h1>
            <p class="text-sm mt-0.5" style="color: var(--text-muted);">Send a message to your subscribers</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-6 p-3 rounded-xl text-sm font-medium" style="background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2);">
        <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
    </div>
    @endif

    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-purple-400">{{ number_format($stats['email']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">Email Subscribers</div>
        </div>
        <div class="glass rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: #25D366;">{{ number_format($stats['whatsapp_number']) }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted);">WhatsApp Subscribers</div>
        </div>
    </div>

    <form method="POST" action="{{ route('user.subscribers.send') }}">
        @csrf

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Channel</h2>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="channel" value="email" x-model="channel" class="sr-only peer">
                    <div class="glass rounded-xl p-4 text-center transition-all peer-checked:ring-2 peer-checked:ring-purple-500 hover:bg-white/[0.03]">
                        <div class="w-12 h-12 rounded-full mx-auto mb-2 flex items-center justify-center" style="background: linear-gradient(135deg, rgba(124,58,237,0.3), rgba(168,85,247,0.2));">
                            <i class="fas fa-envelope text-purple-400 text-lg"></i>
                        </div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Email</p>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ $stats['email'] }} recipients</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="channel" value="whatsapp" x-model="channel" class="sr-only peer">
                    <div class="glass rounded-xl p-4 text-center transition-all peer-checked:ring-2 peer-checked:ring-green-500 hover:bg-white/[0.03]">
                        <div class="w-12 h-12 rounded-full mx-auto mb-2 flex items-center justify-center" style="background: rgba(37,211,102,0.15);">
                            <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
                        </div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">WhatsApp</p>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ $stats['whatsapp_number'] }} recipients</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Message</h2>
            <div class="space-y-4">
                <div x-show="channel === 'email'">
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Subject</label>
                    <input type="text" name="subject" placeholder="Your email subject..." class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1.5 block" style="color: var(--text-muted);">Body</label>
                    <textarea name="body" rows="8" placeholder="Write your message here..." required class="w-full px-3 py-2.5 rounded-xl text-sm outline-none resize-y" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"></textarea>
                </div>
                <input type="hidden" name="filter_type" :value="channel === 'email' ? 'email' : 'whatsapp_number'">
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('user.subscribers.messages') }}" class="text-sm" style="color: var(--text-muted);">
                <i class="fas fa-history mr-1"></i>Message History
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5 flex items-center gap-2" style="background: linear-gradient(135deg, #7c3aed, #a855f7);"
                    onclick="return confirm('Send this message to all active subscribers in the selected channel?')">
                <i class="fas fa-paper-plane"></i>Send Message
            </button>
        </div>
    </form>

    @if($messages->count())
    <div class="mt-8">
        <h2 class="font-semibold mb-4" style="color: var(--text-primary);">Recent Messages</h2>
        <div class="space-y-3">
            @foreach($messages->take(5) as $msg)
            <div class="glass rounded-xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: {{ $msg->channel === 'email' ? 'rgba(124,58,237,0.15)' : 'rgba(37,211,102,0.15)' }};">
                        <i class="{{ $msg->channel === 'email' ? 'fas fa-envelope text-purple-400' : 'fab fa-whatsapp' }} text-sm" style="{{ $msg->channel !== 'email' ? 'color:#25D366' : '' }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ $msg->subject ?: Str::limit($msg->body, 60) }}</p>
                        <p class="text-xs" style="color: var(--text-muted);">{{ $msg->sent_at?->diffForHumans() }} &middot; {{ $msg->sent_count }}/{{ $msg->recipients_count }} sent</p>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0" style="background: rgba(34,197,94,0.1); color: #4ade80;">{{ $msg->status }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
