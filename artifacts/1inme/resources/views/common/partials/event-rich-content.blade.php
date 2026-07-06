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
@endphp

{{-- Organizer card (Task #3674): always shows the host's avatar + name;
     links to their public profile only when they actually have a handle. --}}
@if($host)
    <div class="d-flex align-items-center gap-2 mb-3 p-2 border rounded-3">
        @if($host->avatar)
            <img src="{{ $host->avatar }}" alt="" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
        @else
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-muted" style="width:40px;height:40px;">
                <i class="fas fa-user"></i>
            </div>
        @endif
        <div>
            <div class="text-muted" style="font-size:11px;">Hosted by</div>
            @if($host->handle)
                <a href="{{ route('creator-profile.show', $host->handle) }}" class="fw-semibold text-dark text-decoration-none small">{{ $host->name }}</a>
            @else
                <div class="fw-semibold text-dark small">{{ $host->name }}</div>
            @endif
        </div>
    </div>
@endif

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

@if(!empty($similarEvents) && $similarEvents->isNotEmpty())
    <div class="mb-3">
        <div class="fw-semibold small mb-2">Similar events</div>
        <div class="row g-2">
            @foreach($similarEvents as $rec)
                <div class="col-6">
                    <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none">
                        <div class="border rounded-3 p-2 h-100">
                            <div class="small fw-semibold text-dark">{{ $rec->title }}</div>
                            @if($rec->icsData && $rec->icsData->start_date)
                                <div class="small text-muted">{{ $rec->icsData->start_date->format('M j') }}</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if(!empty($sameHostEvents) && $sameHostEvents->isNotEmpty())
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
        <div class="row g-2">
            @foreach($sameHostEvents as $rec)
                <div class="col-6">
                    <a href="{{ url('/' . $rec->alias) }}" class="text-decoration-none">
                        <div class="border rounded-3 p-2 h-100">
                            <div class="small fw-semibold text-dark">{{ $rec->title }}</div>
                            @if($rec->icsData && $rec->icsData->start_date)
                                <div class="small text-muted">{{ $rec->icsData->start_date->format('M j') }}</div>
                            @endif
                        </div>
                    </a>
                </div>
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
