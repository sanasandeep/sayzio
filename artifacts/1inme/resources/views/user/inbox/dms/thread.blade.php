@extends('user.layouts.app')
@section('title', 'Conversation · ' . ($conversation->viewer->name ?? 'Viewer'))

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.inbox.dms.index') }}" class="inline-flex items-center text-sm text-white/60 hover:text-white mb-4">
        <i class="fas fa-arrow-left mr-2"></i> Back to direct messages
    </a>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4 mb-4 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full overflow-hidden bg-white/5 flex items-center justify-center">
            @if($conversation->viewer && $conversation->viewer->profile_picture)
                <img src="{{ $conversation->viewer->profile_picture }}" alt="" class="w-full h-full object-cover">
            @else
                <i class="fas fa-user text-white/40"></i>
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold truncate">{{ $conversation->viewer->name ?? 'Unknown viewer' }}</p>
            <p class="text-xs text-white/50 truncate">
                {{ $conversation->viewer->email ?? '' }}
                <span class="mx-1">·</span>
                via <i class="fas fa-link mx-1"></i>{{ $conversation->link->alias ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($conversation->isBlocked())
                <form method="POST" action="{{ route('user.inbox.dms.unblock', $conversation->id) }}">
                    @csrf
                    <button class="px-3 py-2 text-xs rounded-lg bg-emerald-500/15 text-emerald-200 border border-emerald-400/30 hover:bg-emerald-500/25">
                        <i class="fas fa-undo mr-1"></i> Unblock
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('user.inbox.dms.block', $conversation->id) }}"
                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Block this conversation?', message: 'The viewer will no longer be able to send you messages here.', confirmText: 'Block', confirmIcon: 'fa-ban', iconClass: 'fa-ban'})"
                      x-data="{ accountWide: false }">
                    @csrf
                    <label class="flex items-center gap-1 text-[11px] text-white/60 mr-2">
                        <input type="checkbox" name="account_wide" value="1" x-model="accountWide" class="accent-rose-500">
                        Block account-wide
                    </label>
                    <button class="px-3 py-2 text-xs rounded-lg bg-rose-500/15 text-rose-200 border border-rose-400/30 hover:bg-rose-500/25">
                        <i class="fas fa-ban mr-1"></i> Block
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Anti-spam status --}}
    @if(!$conversation->owner_replied)
        <div class="mb-4 px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs">
            <i class="fas fa-info-circle mr-1"></i>
            New viewer — they're capped at {{ \App\Modules\Common\Models\ViewerDmConversation::VIEWER_INITIAL_LIMIT }} intro messages
            ({{ $conversation->viewer_msg_count }} sent) until you reply. Your first reply unlocks unlimited replies on both sides.
        </div>
    @endif

    {{-- Thread --}}
    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-4 mb-4 max-h-[60vh] overflow-y-auto space-y-3">
        @forelse($messages as $m)
            <div class="{{ $m->sender_type === 'owner' ? 'flex justify-end' : 'flex justify-start' }}">
                <div class="max-w-[78%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap break-words
                            {{ $m->sender_type === 'owner' ? 'bg-indigo-500/30 text-indigo-50' : 'bg-white/10 text-white' }}">
                    {{ $m->body }}
                    <div class="text-[10px] opacity-60 mt-1 text-right">{{ $m->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <p class="text-center text-sm text-white/40 py-6">No messages yet.</p>
        @endforelse
    </div>

    {{-- AI Companion auto-reply binding --}}
    @if(($companions ?? collect())->isNotEmpty())
        <form method="POST" action="{{ route('user.inbox.dms.auto-reply', $conversation->id) }}"
              class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-3 flex flex-wrap items-center gap-2 text-sm">
            @csrf @method('PUT')
            <i class="fas fa-robot text-blue-300"></i>
            <span class="text-white/80">Auto-reply with</span>
            <select name="companion_id" class="bg-black/30 border border-white/10 rounded-lg px-2 py-1 text-sm text-white">
                <option value="">— Off —</option>
                @foreach($companions as $cmp)
                    <option value="{{ $cmp->id }}" @selected((int) $conversation->auto_reply_companion_id === (int) $cmp->id)>{{ $cmp->name }}</option>
                @endforeach
            </select>
            <button class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-medium">Save</button>
            @if($conversation->auto_reply_companion_id)
                <span class="text-[11px] text-blue-200/70">Drafts run on each viewer message; auto-send only fires if the Companion has it enabled.</span>
            @endif
        </form>
    @endif

    {{-- Composer --}}
    @unless($conversation->isBlocked())
        <form method="POST" action="{{ route('user.inbox.dms.reply', $conversation->id) }}"
              class="rounded-2xl border border-white/10 bg-white/[0.02] p-3 flex gap-2 items-end">
            @csrf
            <textarea name="body" required rows="3" maxlength="5000"
                      placeholder="Type your reply…"
                      class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm outline-none focus:border-white/20 resize-none"></textarea>
            <button class="px-4 py-2.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-sm font-medium text-white">
                <i class="fas fa-paper-plane mr-1"></i> Send
            </button>
        </form>
    @else
        <p class="rounded-2xl border border-rose-500/20 bg-rose-500/5 px-4 py-3 text-sm text-rose-200">
            <i class="fas fa-ban mr-1"></i> This conversation is blocked. Unblock it above to reply.
        </p>
    @endunless
</div>
@endsection
