<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events — Sayzio</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <style>
        body { background:#f8f9ff; }
        .event-card { border-radius:16px; border:1px solid #e5e7eb; transition:box-shadow .15s; height:100%; }
        .event-card:hover { box-shadow:0 8px 24px rgba(61,107,255,0.12); }
        .hero { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:48px 24px; }
    </style>
</head>
<body>
<div class="hero text-center">
    <h1 class="fw-bold">Discover Events</h1>
    <p class="mb-0 opacity-75">Find happenings near you, powered by Sayzio</p>
</div>

<div class="container py-4">
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="text" name="q" class="form-control" placeholder="Search events" value="{{ $q }}">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">All categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c }}" @selected($category === $c)>{{ ucfirst($c) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" id="near-me-btn" class="btn btn-outline-primary w-100">
                <i class="fas fa-location-arrow me-1"></i> Near me
            </button>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <input type="hidden" name="lat" value="{{ $lat }}">
        <input type="hidden" name="lng" value="{{ $lng }}">
    </form>

    @if($nearMe)
        <div class="alert alert-info small">Showing events within {{ $radiusKm }}km of your location.</div>
    @endif

    <div class="row g-3">
        @forelse($events as $event)
            <div class="col-md-4">
                <div class="event-card p-3">
                    <div class="small text-muted mb-1">
                        @if($event->icsData && $event->icsData->start_date)
                            {{ $event->icsData->start_date->format('D, M j · g:i A') }}
                        @endif
                    </div>
                    <h2 class="h6 fw-bold">{{ $event->title }}</h2>
                    @if($event->icsData && $event->icsData->location)
                        <div class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i>{{ $event->icsData->location }}</div>
                    @endif
                    @if($event->eventTicketTiers->isNotEmpty())
                        <div class="small mb-2">
                            From <strong>{{ $event->eventTicketTiers->sortBy('price_cents')->first()->priceLabel() }}</strong>
                        </div>
                    @else
                        <div class="small mb-2 text-success">Free RSVP</div>
                    @endif
                    <a href="{{ url('/' . $event->alias) }}" class="btn btn-sm btn-outline-primary">View event</a>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-5">No events found.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
</div>

<script>
document.getElementById('near-me-btn')?.addEventListener('click', function () {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
        const form = document.querySelector('form');
        form.lat.value = pos.coords.latitude;
        form.lng.value = pos.coords.longitude;
        form.submit();
    });
});
</script>
</body>
</html>
