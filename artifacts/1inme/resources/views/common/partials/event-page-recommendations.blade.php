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

@if(!empty($similarEvents) && $similarEvents->isNotEmpty())
    {{-- Grid-of-image-cards layout, distinct from the horizontal-list
         "More from this host" section below. --}}
    <div class="mb-3">
        <div class="fw-semibold small mb-2">Similar events</div>
        <div class="row g-2">
            @foreach($similarEvents as $rec)
                @php $meta = $eventCardMeta($rec); @endphp
                <div class="col-6">
                    <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none">
                        <div class="border rounded-3 overflow-hidden h-100 position-relative">
                            <div class="ratio ratio-16x9 position-relative">
                                <img src="{{ $eventCardThumb($rec) }}" alt="{{ $rec->title }}" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                                <span class="badge {{ $meta->isPaid ? 'bg-secondary' : 'bg-success' }} position-absolute top-0 end-0 m-2" style="font-size:10px;">{{ $meta->priceLabel }}</span>
                            </div>
                            <div class="p-2">
                                <div class="small fw-semibold text-dark">{{ $rec->title }}</div>
                                @if($rec->icsData && $rec->icsData->start_date)
                                    <div class="small text-muted">{{ $rec->icsData->start_date->format('M j') }}</div>
                                @endif
                                @if($meta->online)
                                    <div class="small text-muted"><i class="fas fa-video me-1"></i>Online</div>
                                @elseif($meta->location)
                                    <div class="small text-muted text-truncate"><i class="fas fa-location-dot me-1"></i>{{ $meta->location }}</div>
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
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold small">More from this host</div>
            @if($link->user && $link->user->handle)
                {{-- Task #3666 — links out to the host's full public events
                     listing, not just this 4-item preview. --}}
                <a href="{{ route('creator-profile.events', $link->user->handle) }}" class="small text-decoration-none">
                    See all events by this host <i class="fas fa-arrow-right"></i>
                </a>
            @endif
        </div>
        <div class="d-flex flex-column gap-2">
            @foreach($sameHostEvents as $rec)
                @php $meta = $eventCardMeta($rec); @endphp
                <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none">
                    <div class="border rounded-3 d-flex align-items-center gap-3 p-2">
                        <div class="rounded-3 overflow-hidden flex-shrink-0" style="width:64px;height:64px;">
                            <img src="{{ $eventCardThumb($rec) }}" alt="{{ $rec->title }}" loading="lazy" class="w-100 h-100" style="object-fit: cover;">
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="small fw-semibold text-dark text-truncate">{{ $rec->title }}</div>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                @if($rec->icsData && $rec->icsData->start_date)
                                    <span class="small text-muted"><i class="far fa-clock me-1"></i>{{ $rec->icsData->start_date->format('M j') }}</span>
                                @endif
                                @if($meta->online)
                                    <span class="small text-muted"><i class="fas fa-video me-1"></i>Online</span>
                                @elseif($meta->location)
                                    <span class="small text-muted text-truncate"><i class="fas fa-location-dot me-1"></i>{{ $meta->location }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $meta->isPaid ? 'bg-secondary' : 'bg-success' }} flex-shrink-0" style="font-size:10px;">{{ $meta->priceLabel }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif
