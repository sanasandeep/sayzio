    @php
        $eventLinkId = $s['event_link_id'] ?? null;
        $eventLink   = $eventLinkId
            ? \App\Modules\User\Models\Link::where('id', $eventLinkId)
                ->where('user_id', $block->link?->user_id ?? 0)
                ->where('type', 'ics')->with('icsData')->first()
            : null;
    @endphp
    <div class="mb-4 glass-block rounded-xl p-4 text-left" style="background:#fff; color:#111;">
        @if(!$eventLink)
            <div class="text-xs text-center text-white/60 py-2">RSVP block not configured.</div>
        @elseif(!\App\Modules\Common\Controllers\RedirectController::isRsvpAvailable($eventLink))
            <div class="text-xs text-center text-white/60 py-2">RSVP collection is disabled for this event.</div>
        @else
            <div class="mb-3">
                <div class="text-xs uppercase tracking-wider opacity-60">{{ $s['heading'] ?? 'RSVP to' }}</div>
                <div class="font-bold text-base">{{ $eventLink->title }}</div>
                @if($eventLink->icsData)
                    <div class="text-xs opacity-70">
                        <i class="far fa-clock me-1"></i>
                        {{ \Carbon\Carbon::parse($eventLink->icsData->starts_at)->format('M j, Y · g:i A') }}
                    </div>
                @endif
            </div>
            @include('common.partials.rsvp-form-fields', [
                'link' => $eventLink,
                'action' => url('/' . $eventLink->alias . '/rsvp'),
                'sourceTag' => 'biolink_block:' . $block->id,
            ])
        @endif
    </div>
