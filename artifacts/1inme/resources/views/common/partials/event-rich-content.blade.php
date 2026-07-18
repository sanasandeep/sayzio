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
    // Task #5023: agenda + documents.
    $agendaItems = $ics ? (array) $ics->agenda : [];
    $eventDocuments = $ics ? (array) $ics->documents : [];
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

{{-- Task #5023: Event agenda --}}
@if(!empty($agendaItems))
    @php
        $hasDay = collect($agendaItems)->contains(fn($it) => !empty($it['day']));
        $byDay = collect($agendaItems)->groupBy(fn($it) => (int) ($it['day'] ?? 0));
    @endphp
    <div class="event-agenda-section mb-4">
        <div class="event-agenda-heading d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-list-ul" style="color:var(--c-primary,#6d5fff);"></i>
            <span class="fw-semibold">Agenda</span>
        </div>
        @foreach($byDay as $dayNum => $items)
            @if($hasDay && $dayNum > 0)
                <div class="event-agenda-day-label small text-muted mb-1 mt-2 text-uppercase fw-semibold" style="letter-spacing:.06em;">Day {{ $dayNum }}</div>
            @endif
            @foreach($items as $item)
                <div class="event-agenda-item d-flex gap-3 mb-2">
                    @if(!empty($item['time']))
                        <div class="event-agenda-time small" style="min-width:5rem;color:var(--text-muted,#94a3b8);white-space:nowrap;">
                            {{ $item['time'] }}@if(!empty($item['end_time'])) – {{ $item['end_time'] }}@endif
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="small fw-semibold" style="color:var(--text-primary,#f8fafc);">{{ $item['title'] }}</div>
                        @if(!empty($item['description']))
                            <div class="small" style="color:var(--text-muted,#94a3b8);white-space:pre-line;">{{ $item['description'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
@endif

{{-- Task #5023: Event documents --}}
@if(!empty($eventDocuments))
    <div class="event-documents-section mb-4">
        <div class="event-documents-heading d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-file-download" style="color:var(--c-primary,#6d5fff);"></i>
            <span class="fw-semibold">Documents</span>
        </div>
        <div class="event-documents-list d-flex flex-column gap-2">
            @foreach($eventDocuments as $doc)
                @php
                    $docUrl = url('/f/' . ($doc['file_id'] ?? 0) . '/' . ($doc['filename'] ?? ''));
                    $sizeLabel = '';
                    if (!empty($doc['size_bytes'])) {
                        $bytes = (int) $doc['size_bytes'];
                        $sizeLabel = $bytes >= 1048576 ? number_format($bytes/1048576, 1).' MB' : max(1, (int)round($bytes/1024)).' KB';
                    }
                    $mime = $doc['mime'] ?? '';
                    $docIcon = str_contains($mime, 'pdf') ? 'fa-file-pdf' : (str_contains($mime, 'word') || str_contains($mime, 'document') ? 'fa-file-word' : (str_contains($mime, 'sheet') || str_contains($mime, 'excel') ? 'fa-file-excel' : (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint') ? 'fa-file-powerpoint' : (str_contains($mime, 'image') ? 'fa-file-image' : 'fa-file-alt'))));
                @endphp
                <a href="{{ $docUrl }}" target="_blank" rel="noopener" class="event-document-item d-flex align-items-center gap-2 text-decoration-none p-2 rounded" style="border:1px solid var(--border-glass,rgba(255,255,255,.1));background:var(--bg-card,rgba(0,0,0,.15));">
                    <i class="fas {{ $docIcon }} text-lg" style="color:var(--c-primary,#6d5fff);width:1.2rem;text-align:center;"></i>
                    <div class="flex-1 min-width-0">
                        <div class="small fw-semibold text-truncate" style="color:var(--text-primary,#f8fafc);">{{ $doc['label'] ?? $doc['filename'] ?? 'Document' }}</div>
                        @if($sizeLabel)
                            <div class="x-small" style="color:var(--text-muted,#94a3b8);font-size:.72rem;">{{ $sizeLabel }}</div>
                        @endif
                    </div>
                    <i class="fas fa-download small" style="color:var(--text-muted,#94a3b8);"></i>
                </a>
            @endforeach
        </div>
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
