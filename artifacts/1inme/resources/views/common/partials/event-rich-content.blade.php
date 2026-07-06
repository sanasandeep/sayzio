{{-- Cover image, gallery, info sections, hashtags & Interested signal
     (Task #3593). Included by both the RSVP page and the ticketed event
     page so the two public event surfaces stay visually in sync. Expects
     `$link` (with icsData loaded) in scope. --}}
@php
    $ics = $link->icsData;
    $gallery = $ics ? (array) $ics->gallery : [];
    $infoSections = $ics ? (array) $ics->info_sections : [];
    $hashtagList = $ics ? $ics->hashtagList() : [];
    $host = $link->relationLoaded('user') ? $link->user : $link->user()->first();
    $organizer = $host ? $host->organizerProfile() : null;
@endphp

{{-- Organizer/host card: rendered inline here by default (used by the RSVP
     page). The public event page (event-page.blade.php) renders this same
     card standalone in its right-hand column instead, and passes
     `hideHostCard` so it isn't duplicated here (Task #3731). --}}
@unless($hideHostCard ?? false)
    @include('common.partials.event-host-card', ['host' => $host, 'organizer' => $organizer])
@endunless

@if($ics && $ics->cover_image_url)
    <div class="mb-3">
        <img src="{{ $ics->cover_image_url }}" alt="{{ $link->title }}" class="w-100 rounded-3" style="max-height:280px;object-fit:cover;">
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        @foreach($hashtagList as $ht)
            <a href="{{ route('events.index', ['tag' => $ht]) }}" class="badge bg-light text-dark border text-decoration-none me-1">#{{ $ht }}</a>
        @endforeach
    </div>
    <div id="event-interest-widget" data-alias="{{ $link->alias }}" class="small">
        <button type="button" class="btn btn-sm btn-outline-success" data-interest="interested"><i class="fas fa-star me-1"></i> Interested (<span id="interested-count">{{ $interestCounts['interested'] ?? 0 }}</span>)</button>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" data-interest="not_interested">Not interested</button>
    </div>
</div>

@if(!empty($gallery))
    <div class="row g-2 mb-3">
        @foreach($gallery as $img)
            <div class="col-4">
                <img src="{{ $img }}" alt="" class="w-100 rounded-2" style="height:90px;object-fit:cover;">
            </div>
        @endforeach
    </div>
@endif

@if(!empty($infoSections))
    <div class="mb-3">
        @foreach($infoSections as $section)
            @continue(empty($section['title']) && empty($section['body']))
            <div class="mb-2">
                @if(!empty($section['title']))<div class="fw-semibold small">{{ $section['title'] }}</div>@endif
                @if(!empty($section['body']))<div class="small text-muted" style="white-space:pre-line">{{ $section['body'] }}</div>@endif
            </div>
        @endforeach
    </div>
@endif

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

<script>
(function () {
    var widget = document.getElementById('event-interest-widget');
    if (!widget) return;
    var alias = widget.dataset.alias;
    var token = document.querySelector('meta[name="csrf-token"]')?.content;
    widget.querySelectorAll('[data-interest]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fetch('/' + alias + '/interest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ status: btn.dataset.interest }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.counts) {
                    var el = document.getElementById('interested-count');
                    if (el) el.textContent = data.counts.interested;
                }
            }).catch(function () {});
        });
    });
})();
</script>
