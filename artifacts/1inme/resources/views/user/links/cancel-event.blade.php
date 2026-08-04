@extends('user.layouts.app')

@section('title', 'Cancel event: ' . $link->title)

@section('content')
<style>
    /* Theme-aware colors (task #6684): the notify-guests card + buttons used
       dark-mode-hardcoded rgba(255,255,255,...) / #ef4444 styles. */
    .ce-icon { background: rgba(239,68,68,0.15); color: #ef4444; }
    html.light-mode .ce-icon { color: #dc2626; }
    .ce-notify-card { background: var(--bg-glass, rgba(255,255,255,0.04)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10)); }
    .ce-btn-danger { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.30); color: #ef4444; }
    html.light-mode .ce-btn-danger { color: #b91c1c; border-color: rgba(185,28,28,0.35); }
</style>
<div class="max-w-2xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Cancel event',
        'subtitle' => $link->title,
        'icon' => 'fa-ban',
        'back' => route('user.links.ics.edit', $link),
    ])

    <div class="card-premium p-5 mb-6">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ce-icon">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Cancel this event?</div>
                <div class="text-xs mt-1 leading-relaxed" style="color: var(--text-muted);">
                    Cancelling marks the event as called off. Your public event page will show a
                    "cancelled" banner, and new RSVPs and ticket sales will be blocked. Reminders and
                    automatic waitlist promotion stop too. You can reactivate the event later if you
                    change your mind; nothing is deleted.
                    <span class="block mt-1.5">Ticket refunds are not automatic. You can issue refunds from the RSVPs / ticket tools if needed.</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('user.links.ics.cancel.confirm', $link) }}" class="mt-5">
            @csrf

            <label class="flex items-start gap-2.5 p-3 rounded-lg cursor-pointer mb-4 ce-notify-card">
                <input type="checkbox" name="notify_guests" value="1" class="mt-0.5">
                <span>
                    <span class="block text-sm font-semibold" style="color: var(--text-primary);">Notify all guests now</span>
                    <span class="block text-xs mt-0.5" style="color: var(--text-muted);">
                        <i class="fas fa-users mr-1"></i>{{ $recipientCount }} guest(s) will receive a cancellation email immediately.
                        Leave unchecked to review and send the message yourself afterwards.
                    </span>
                </span>
            </label>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-end">
                <a href="{{ route('user.links.ics.edit', $link) }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold text-center" style="color: var(--text-muted);">
                    Keep event
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5 ce-btn-danger"
                        onsubmit="return true">
                    <i class="fas fa-ban"></i> Cancel this event
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
