{{-- Organizer/host card (Task #3674, extended by Task #3699 and #3731):
     when the host has filled in a reusable organizer profile, show the
     richer card (logo, name, description, website, contact person,
     socials, address). Otherwise fall back to the original simple avatar +
     name card.

     Extracted from event-rich-content.blade.php so the public event page
     (event-page.blade.php) can render it standalone in its right-hand
     column while the RSVP page keeps it inline via event-rich-content.
     Expects `$host` and `$organizer` (from Host::organizerProfile()) in
     scope. --}}
@if($host && $organizer && $organizer['filled'])
    <div class="mb-3 p-3 border rounded-3">
        <div class="d-flex align-items-start gap-2">
            @if($organizer['logo'])
                <img src="{{ $organizer['logo'] }}" alt="" class="rounded-3" style="width:52px;height:52px;object-fit:cover;">
            @elseif($host->avatar)
                <img src="{{ $host->avatar }}" alt="" class="rounded-circle" style="width:52px;height:52px;object-fit:cover;">
            @else
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-muted" style="width:52px;height:52px;">
                    <i class="fas fa-user"></i>
                </div>
            @endif
            <div class="flex-grow-1">
                <div class="text-muted" style="font-size:11px;">Hosted by</div>
                @php $orgName = $organizer['name'] ?: $host->name; @endphp
                @if($host->handle)
                    <a href="{{ route('creator-profile.show', $host->handle) }}" class="fw-semibold text-dark text-decoration-none small d-block">{{ $orgName }}</a>
                @else
                    <div class="fw-semibold text-dark small">{{ $orgName }}</div>
                @endif
                @if($organizer['description'])
                    <div class="text-muted small mt-1">{{ $organizer['description'] }}</div>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-2" style="font-size:12px;">
            @if($organizer['website'])
                <a href="{{ $organizer['website'] }}" target="_blank" rel="noopener" class="text-decoration-none">
                    <i class="fas fa-globe me-1"></i>Website
                </a>
            @endif
            @if($organizer['contact_email'])
                <a href="mailto:{{ $organizer['contact_email'] }}" class="text-decoration-none">
                    <i class="fas fa-envelope me-1"></i>{{ $organizer['contact_name'] ?: $organizer['contact_email'] }}
                </a>
            @elseif($organizer['contact_phone'])
                <a href="tel:{{ $organizer['contact_phone'] }}" class="text-decoration-none">
                    <i class="fas fa-phone me-1"></i>{{ $organizer['contact_name'] ?: $organizer['contact_phone'] }}
                </a>
            @elseif($organizer['contact_name'])
                <span><i class="fas fa-user-tie me-1"></i>{{ $organizer['contact_name'] }}</span>
            @endif
            @if($organizer['contact_phone'] && $organizer['contact_email'])
                <a href="tel:{{ $organizer['contact_phone'] }}" class="text-decoration-none">
                    <i class="fas fa-phone me-1"></i>{{ $organizer['contact_phone'] }}
                </a>
            @endif
            @if($organizer['address'])
                <span><i class="fas fa-location-dot me-1"></i>{{ $organizer['address'] }}</span>
            @endif
        </div>

        @if(!empty($organizer['socials']))
            <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($organizer['socials'] as $platform => $value)
                    @php
                        $isUrl = str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
                        $isEmail = $platform === 'email';
                        $href = $isEmail ? ('mailto:' . $value) : ($isUrl ? $value : ('https://' . ltrim($value, '@')));
                    @endphp
                    <a href="{{ $href }}" target="_blank" rel="noopener" class="text-decoration-none small border rounded-pill px-2 py-1">
                        {{ ucfirst($platform) }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@elseif($host)
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
