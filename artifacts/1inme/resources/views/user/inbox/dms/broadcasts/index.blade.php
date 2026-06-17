@extends('user.layouts.app')
@section('title', 'Mass DM broadcasts')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Mass DM broadcasts',
        'subtitle' => 'Send a one-off message to followers, subscribers or a tier',
        'icon'     => 'fa-bullhorn',
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('user.inbox.dms.broadcasts.store') }}" class="space-y-3 bg-white/5 border border-white/10 p-5 rounded-2xl mb-6">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="text-sm">
                <span style="color: var(--text-muted);">Audience</span>
                <select name="audience_kind" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    @foreach($audiences as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm sm:col-span-2">
                <span style="color: var(--text-muted);">Tier (only when audience = "Tier")</span>
                <select name="audience_value" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    <option value="">—</option>
                    @foreach($tiers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="block text-sm">
            <span style="color: var(--text-muted);">Message</span>
            <textarea name="body" rows="4" required maxlength="5000"
                      class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
                      placeholder="Hey friends — just dropped a new behind-the-scenes set 🔥"></textarea>
        </label>

        <details class="text-sm">
            <summary class="cursor-pointer text-violet-400">Attach a (lockable) file</summary>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="url" name="attachment_url" placeholder="https://… (file URL)" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                <input type="url" name="attachment_thumb_url" placeholder="https://… (thumb / blur preview)" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                <select name="attachment_kind" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    <option value="">— Type —</option>
                    <option value="image">Image</option>
                    <option value="gallery">Gallery</option>
                    <option value="video">Video</option>
                    <option value="audio">Audio</option>
                    <option value="voice">Voice note</option>
                    <option value="file">File</option>
                </select>
                <input type="number" name="attachment_lock_price_cents" min="0" max="100000" placeholder="Unlock price (cents) — 0 = free" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            </div>
        </details>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-white/10 border border-white/10 text-sm">Save draft</button>
            <button type="submit" name="send_now" value="1" class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold">Send now</button>
        </div>
    </form>

    <div class="space-y-2">
        @forelse($broadcasts as $b)
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 flex items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="text-sm">{{ \Illuminate\Support\Str::limit($b->body, 200, '…') }}</div>
                    <div class="text-[11px] mt-1" style="color: var(--text-muted);">
                        Audience: {{ $b->audience_kind }}@if($b->audience_value) (#{{ $b->audience_value }})@endif ·
                        Status: <span class="font-semibold">{{ $b->status }}</span> ·
                        @if($b->status === 'sent')
                            Sent to {{ $b->sent_count }} / {{ $b->recipients_count }}@if($b->failed_count) ({{ $b->failed_count }} failed)@endif
                            on {{ $b->sent_at?->diffForHumans() }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($b->status !== 'sent')
                        <form method="POST" action="{{ route('user.inbox.dms.broadcasts.send', $b) }}">
                            @csrf
                            <button class="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold">Send</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('user.inbox.dms.broadcasts.destroy', $b) }}" onsubmit="return confirm('Delete this broadcast?');">
                        @csrf @method('DELETE')
                        <button class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-rose-300 text-xs">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-sm" style="color: var(--text-muted);">No broadcasts yet.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $broadcasts->links() }}</div>
</div>
@endsection
