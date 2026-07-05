<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $link->title }} — Tickets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <style>
        body { background:#f5f3ff; min-height:100vh; padding:24px; }
        .event-card { max-width:640px; margin:0 auto; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(61,107,255,0.12); overflow:hidden; }
        .event-header { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:32px; }
        .event-body { padding:28px; background:#fff; }
        .tier-card { border:2px solid #e5e7eb; border-radius:14px; padding:16px; margin-bottom:14px; }
        .tier-card.selected { border-color:#3d6bff; background:#f5f7ff; }
        .btn-purple { background:#3d6bff; color:#fff; border:none; }
        .btn-purple:hover { background:#2342c7; color:#fff; }
    </style>
</head>
<body>
<div class="card event-card">
    <div class="event-header">
        <div class="small opacity-75 mb-1"><i class="fas fa-ticket-alt me-1"></i> You're invited to</div>
        <h1 class="h3 fw-bold mb-2">{{ $link->title }}</h1>
        @if($link->icsData && $link->icsData->start_date)
            <div class="small">
                <i class="far fa-clock me-1"></i>
                {{ $link->icsData->start_date->setTimezone(new \DateTimeZone($link->icsData->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                @if($link->icsData->location) · <i class="fas fa-map-marker-alt ms-1 me-1"></i>{{ $link->icsData->location }}@endif
            </div>
        @endif
    </div>

    <div class="event-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if($link->icsData && $link->icsData->description)
            <p class="text-muted">{{ $link->icsData->description }}</p>
        @endif

        <form method="POST" action="{{ route('redirect.event.buy', $link->alias) }}" id="ticket-form">
            @csrf
            <h2 class="h6 fw-bold mb-3">Choose your tickets</h2>

            @forelse($tiers as $tier)
                <label class="tier-card d-block">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tier_id" value="{{ $tier->id }}" required
                                @checked($loop->first) @disabled($tier->isSoldOut() || !$tier->isOnSale())>
                            <span class="fw-semibold">{{ $tier->name }}</span>
                            @if($tier->isSoldOut())<span class="badge bg-secondary ms-2">Sold out</span>@endif
                        </div>
                        <div class="fw-bold">{{ $tier->priceLabel() }}</div>
                    </div>
                    @if($tier->description)<div class="small text-muted mt-1">{{ $tier->description }}</div>@endif
                    @if($tier->remainingCapacity() !== null)
                        <div class="small text-muted">{{ $tier->remainingCapacity() }} remaining</div>
                    @endif
                </label>
            @empty
                <div class="alert alert-warning">No tickets are available for this event right now.</div>
            @endforelse

            @if($tiers->isNotEmpty())
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" max="20" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <label class="form-label small">Full name</label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                    </div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <label class="form-label small">Phone (optional)</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-purple w-100 mt-3 fw-semibold">
                    <i class="fas fa-ticket-alt me-1"></i> Get tickets
                </button>
            @endif
        </form>

        <div class="text-center mt-3 small text-muted">
            <a href="{{ url('/' . $link->alias . '?ics=1') }}" class="text-muted text-decoration-none">
                <i class="fas fa-download me-1"></i> Add to calendar (.ics)
            </a>
        </div>
    </div>
</div>
</body>
</html>
