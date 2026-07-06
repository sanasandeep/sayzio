{{-- Task #3769: "Similar events" / "More from this host" recommendation
     sections, extracted out of event-rich-content.blade.php so they can be
     rendered both server-side (cache hit) and lazily via an async fetch
     (cache miss) without duplicating markup. Expects `$link`, `$similarEvents`,
     `$sameHostEvents` in scope. --}}
@php
    // Task #3695 — thumbnail resolver shared by both recommendation
    // sections: cover image first, then the first gallery image, then a
    // neutral placeholder so cards never show a broken image icon.
    $eventCardThumb = function ($rec) {
        $recIcs = $rec->icsData ?? null;
        if ($recIcs && $recIcs->cover_image_url) {
            return $recIcs->cover_image_url;
        }
        $recGallery = $recIcs ? array_values((array) $recIcs->gallery) : [];
        $first = $recGallery[0] ?? null;
        if (!empty($first)) {
            return $first;
        }
        return asset('images/events/event-cover-placeholder.svg');
    };

    // Task #3768 — shared location + Free/Paid resolver for both
    // recommendation sections. Paid = ticketing enabled with at least one
    // active tier priced above zero (mirrors EventTicketTier::isFree());
    // otherwise the event reads as Free. Degrades cleanly when location or
    // ticket data is absent.
    $eventCardMeta = function ($rec) {
        $recSettings = (array) ($rec->settings ?? []);
        $online = !empty($recSettings['is_online']);
        $location = $rec->icsData->location ?? null;

        $tiers = $rec->relationLoaded('eventTicketTiers') ? $rec->eventTicketTiers : collect();
        $ticketingEnabled = !empty($recSettings['ticketing_enabled']);
        $paidTiers = $ticketingEnabled ? $tiers->filter(fn ($t) => !$t->isFree()) : collect();
        $isPaid = $paidTiers->isNotEmpty();
        $priceLabel = $isPaid ? $paidTiers->sortBy('price_cents')->first()->priceLabel() : 'Free';

        return (object) compact('online', 'location', 'isPaid', 'priceLabel');
    };
@endphp

{{-- Task #3794 — shared structural styling for the recommendation cards.
     Kept theme-color-agnostic (neutral rgba(0,0,0,…) shadows + a plain
     white/near-transparent card surface) so it reads correctly by default
     on the light RSVP page; the dark event page repaints surface/border
     colors via its own scoped `.ev-rich` rules in event-page.blade.php. --}}
@once
    <style>
        .ev-rec-heading { font-size: .8125rem; font-weight: 700; letter-spacing: .02em; }
        .ev-rec-card {
            border-radius: 1.1rem !important;
            background: rgba(255,255,255,0.6);
            box-shadow: 0 1px 2px rgba(15,23,42,0.05);
            transition: transform .2s ease, box-shadow .25s ease, border-color .2s ease;
        }
        a:hover > .ev-rec-card {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(15,23,42,0.14);
        }
        a:hover > .ev-rec-card.ev-rec-card-list { transform: translateY(-2px); }
        .ev-rec-thumb { position: relative; }
        .ev-rec-thumb img { transition: transform .35s ease; }
        a:hover .ev-rec-thumb img { transform: scale(1.04); }
        .ev-rec-badge {
            border-radius: 999px !important;
            font-size: 10.5px !important;
            font-weight: 700;
            letter-spacing: .03em;
            padding: .35em .8em;
            backdrop-filter: blur(6px);
            box-shadow: 0 1px 4px rgba(15,23,42,0.18);
        }
        .ev-rec-badge-free { background: rgba(236,253,245,0.92) !important; color: #047857 !important; border: 1px solid rgba(5,150,105,0.25); }
        .ev-rec-badge-paid { background: rgba(255,255,255,0.9) !important; color: #334155 !important; border: 1px solid rgba(51,65,85,0.15); }
        .ev-rec-title { line-height: 1.3; letter-spacing: .005em; }
        .ev-rec-meta { line-height: 1.4; }
        .ev-rec-card-list-thumb { width: 72px; height: 72px; border-radius: .85rem; }
    </style>
@endonce

@if(!empty($similarEvents) && $similarEvents->isNotEmpty())
    {{-- Grid-of-image-cards layout, distinct from the horizontal-list
         "More from this host" section below. --}}
    <div class="mb-4">
        <div class="ev-rec-heading mb-3">Similar events</div>
        <div class="row g-3">
            @foreach($similarEvents as $rec)
                @php $meta = $eventCardMeta($rec); @endphp
                <div class="col-6">
                    <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none d-block">
                        <div class="border overflow-hidden h-100 position-relative ev-rec-card">
                            <div class="ratio ratio-16x9 position-relative ev-rec-thumb overflow-hidden">
                                <img src="{{ $eventCardThumb($rec) }}" alt="{{ $rec->title }}" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                                <span class="badge ev-rec-badge {{ $meta->isPaid ? 'ev-rec-badge-paid' : 'ev-rec-badge-free' }} position-absolute top-0 end-0 m-2">{{ $meta->priceLabel }}</span>
                            </div>
                            <div class="p-3">
                                <div class="small fw-semibold text-dark ev-rec-title">{{ $rec->title }}</div>
                                @if($rec->icsData && $rec->icsData->start_date)
                                    <div class="small text-muted mt-1 ev-rec-meta">{{ $rec->icsData->start_date->format('M j') }}</div>
                                @endif
                                @if($meta->online)
                                    <div class="small text-muted mt-1 ev-rec-meta"><i class="fas fa-video me-1"></i>Online</div>
                                @elseif($meta->location)
                                    <div class="small text-muted text-truncate mt-1 ev-rec-meta"><i class="fas fa-location-dot me-1"></i>{{ $meta->location }}</div>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if(!empty($sameHostEvents) && $sameHostEvents->isNotEmpty())
    {{-- Horizontal-list layout, distinct from the "Similar events" grid
         above so the two sections read as visibly different sections. --}}
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="ev-rec-heading">More from this host</div>
            @if($link->user && $link->user->handle)
                {{-- Task #3666 — links out to the host's full public events
                     listing, not just this 4-item preview. --}}
                <a href="{{ route('creator-profile.events', $link->user->handle) }}" class="small text-decoration-none">
                    See all events by this host <i class="fas fa-arrow-right"></i>
                </a>
            @endif
        </div>
        <div class="d-flex flex-column gap-3">
            @foreach($sameHostEvents as $rec)
                @php $meta = $eventCardMeta($rec); @endphp
                <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none d-block">
                    <div class="border d-flex align-items-center gap-3 p-3 ev-rec-card ev-rec-card-list">
                        <div class="rounded-3 overflow-hidden flex-shrink-0 ev-rec-thumb ev-rec-card-list-thumb">
                            <img src="{{ $eventCardThumb($rec) }}" alt="{{ $rec->title }}" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="small fw-semibold text-dark text-truncate ev-rec-title">{{ $rec->title }}</div>
                            <div class="d-flex flex-wrap gap-3 mt-1">
                                @if($rec->icsData && $rec->icsData->start_date)
                                    <span class="small text-muted ev-rec-meta"><i class="far fa-clock me-1"></i>{{ $rec->icsData->start_date->format('M j') }}</span>
                                @endif
                                @if($meta->online)
                                    <span class="small text-muted ev-rec-meta"><i class="fas fa-video me-1"></i>Online</span>
                                @elseif($meta->location)
                                    <span class="small text-muted text-truncate ev-rec-meta"><i class="fas fa-location-dot me-1"></i>{{ $meta->location }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="badge ev-rec-badge {{ $meta->isPaid ? 'ev-rec-badge-paid' : 'ev-rec-badge-free' }} flex-shrink-0">{{ $meta->priceLabel }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif
