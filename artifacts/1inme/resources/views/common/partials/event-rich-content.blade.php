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

{{-- Task #3769: "Similar events" / "More from this host" are optional
     recommendation widgets, not core to this page. When they were already
     cached server-side, render them immediately here; otherwise (cache
     miss) render nothing now and lazily fetch them client-side below —
     the slow lookups behind them must never run inline on this request. --}}
<div id="event-recommendations" data-alias="{{ $link->alias }}" data-pending="{{ ($extrasPending ?? false) ? '1' : '0' }}">
    @unless($extrasPending ?? false)
        @include('common.partials.event-page-recommendations', ['link' => $link, 'similarEvents' => $similarEvents ?? collect(), 'sameHostEvents' => $sameHostEvents ?? collect()])
    @endunless
</div>

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

// Task #3769: "Similar events" / "More from this host" degrade to a lazy
// client-side fetch when they weren't already cached server-side, so the
// slow recommendation lookups behind them never block this page's render.
(function () {
    var container = document.getElementById('event-recommendations');
    if (!container || container.dataset.pending !== '1') return;
    var alias = container.dataset.alias;
    fetch('/' + alias + '/event-extras', { headers: { 'Accept': 'text/html' } })
        .then(function (r) { return r.ok ? r.text() : ''; })
        .then(function (html) { if (html) container.innerHTML = html; })
        .catch(function () {});
})();
</script>
