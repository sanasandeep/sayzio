@extends('user.layouts.app')

@section('title', 'Message guests: ' . $link->title)

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Message guests',
        'subtitle' => $link->title,
        'icon' => 'fa-paper-plane',
        'back' => route('user.links.rsvps.index', $link),
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    <div class="card-premium p-5 mb-6"
         x-data="{
            audience: 'all_rsvps',
            counts: @js($counts),
            subject: '',
            message: '',
            get count() { return this.counts[this.audience] ?? 0; },
            preset() {
                this.subject = {{ \Illuminate\Support\Js::from('Cancelled: ' . ($link->title ?: 'our event')) }};
                this.message = {{ \Illuminate\Support\Js::from('We\'re sorry to share that this event has been cancelled. We apologise for any inconvenience. If you have any questions, please reply to this email.') }};
                this.audience = 'all_rsvps';
            }
         }"
         @if(request()->query('preset') === 'cancellation') x-init="preset()" @endif>
        <form method="POST" action="{{ route('user.links.ics.broadcast.send', $link) }}"
              @submit="if (count === 0) { $event.preventDefault(); return false; }"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Send this message to your guests?', message: 'This emails every guest in the selected audience. This cannot be undone.', confirmText: 'Send message', confirmIcon: 'fa-paper-plane', iconClass: 'fa-paper-plane'})">
            @csrf

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Audience</label>
                <select name="audience" x-model="audience"
                        class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
                    @foreach($audiences as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="text-xs mt-1.5" style="color: var(--text-muted);">
                    <i class="fas fa-users mr-1"></i>
                    <span x-text="count"></span> recipient(s) will receive this message.
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Subject</label>
                <input type="text" name="subject" x-model="subject" required maxlength="200"
                       placeholder="e.g. Venue has moved"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Message</label>
                <textarea name="message" x-model="message" required maxlength="5000" rows="6"
                          placeholder="Write your update to guests…"
                          class="w-full px-3 py-2 rounded-lg text-sm"
                          style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);"></textarea>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                <button type="button" @click="preset()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5"
                        style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                    <i class="fas fa-ban"></i> Use cancellation notice
                </button>
                <button type="submit" :disabled="count === 0" :style="count === 0 ? 'opacity:.5;cursor:not-allowed' : ''"
                        class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5 btn-primary">
                    <i class="fas fa-paper-plane"></i> Send to <span x-text="count"></span> guest(s)
                </button>
            </div>
        </form>
    </div>

    <div class="card-premium overflow-hidden">
        <div class="px-5 py-3 text-xs uppercase tracking-wider font-semibold" style="color: var(--text-faint); border-bottom: 1px solid rgba(255,255,255,0.08);">
            Past broadcasts
        </div>
        @forelse($broadcasts as $b)
            <div class="px-5 py-3 flex items-start justify-between gap-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $b->subject }}</div>
                    <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                        {{ $audiences[$b->audience] ?? $b->audience }} · {{ $b->recipients_count }} recipient(s)
                    </div>
                </div>
                <div class="text-xs whitespace-nowrap" style="color: var(--text-muted);">{{ $b->created_at?->diffForHumans() }}</div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-xs" style="color: var(--text-muted);">
                No messages sent yet.
            </div>
        @endforelse
    </div>
</div>
@endsection
