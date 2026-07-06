{{-- Organizer/host card (Task #3674, extended by Task #3699, #3731 and
     #3801): when the host has filled in a reusable organizer profile, show
     the richer card (logo, name, description, website, contact person,
     socials, address). Otherwise fall back to the original simple avatar +
     name card.

     Extracted from event-rich-content.blade.php so the public event page
     (event-page.blade.php) can render it standalone in its right-hand
     column while the RSVP page keeps it inline via event-rich-content. The
     card supplies its own background/border box (rather than relying on a
     wrapper) since it is used both bare (RSVP page) and inside a themed
     section (event page) — the box itself uses theme-neutral rgba tones
     plus Bootstrap classes (text-dark/text-muted/border) that both surfaces
     already restyle for their theme via the shared .ev-rich overrides.
     Expects `$host` and `$organizer` (from Host::organizerProfile()) in
     scope. --}}
@if($host && $organizer && $organizer['filled'])
    <div class="mb-3 p-3 rounded-3" style="background:rgba(120,128,150,0.06); border:1px solid rgba(120,128,150,0.18);">
        <div class="d-flex align-items-start gap-3">
            @if($organizer['logo'])
                <img src="{{ $organizer['logo'] }}" alt="" class="rounded-3 flex-shrink-0" style="width:56px;height:56px;object-fit:cover;box-shadow:0 0 0 3px rgba(61,107,255,0.12),0 0 0 1px rgba(61,107,255,0.4);">
            @elseif($host->avatar)
                <img src="{{ $host->avatar }}" alt="" class="rounded-circle flex-shrink-0" style="width:56px;height:56px;object-fit:cover;box-shadow:0 0 0 3px rgba(61,107,255,0.12),0 0 0 1px rgba(61,107,255,0.4);">
            @else
                <img src="{{ asset('images/events/host-avatar-placeholder.svg') }}" alt="" class="rounded-circle flex-shrink-0" style="width:56px;height:56px;object-fit:cover;box-shadow:0 0 0 3px rgba(61,107,255,0.12),0 0 0 1px rgba(61,107,255,0.4);">
            @endif
            <div class="flex-grow-1 min-w-0">
                <div class="text-muted fw-semibold" style="font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;">Hosted by</div>
                @php $orgName = $organizer['name'] ?: $host->name; @endphp
                @if($host->handle)
                    <a href="{{ route('creator-profile.show', $host->handle) }}" class="fw-bold text-dark text-decoration-none d-block" style="font-size:15px;">{{ $orgName }}</a>
                @else
                    <div class="fw-bold text-dark" style="font-size:15px;">{{ $orgName }}</div>
                @endif
                @if($organizer['description'])
                    <div class="text-muted small mt-1">{{ $organizer['description'] }}</div>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            @if($organizer['website'])
                <a href="{{ $organizer['website'] }}" target="_blank" rel="noopener" class="text-decoration-none small border rounded-pill px-2 py-1">
                    <i class="fas fa-globe me-1"></i>Website
                </a>
            @endif
            @if($organizer['contact_email'])
                <a href="mailto:{{ $organizer['contact_email'] }}" class="text-decoration-none small border rounded-pill px-2 py-1">
                    <i class="fas fa-envelope me-1"></i>{{ $organizer['contact_name'] ?: $organizer['contact_email'] }}
                </a>
            @elseif($organizer['contact_phone'])
                <a href="tel:{{ $organizer['contact_phone'] }}" class="text-decoration-none small border rounded-pill px-2 py-1">
                    <i class="fas fa-phone me-1"></i>{{ $organizer['contact_name'] ?: $organizer['contact_phone'] }}
                </a>
            @elseif($organizer['contact_name'])
                <span class="small border rounded-pill px-2 py-1"><i class="fas fa-user-tie me-1"></i>{{ $organizer['contact_name'] }}</span>
            @endif
            @if($organizer['contact_phone'] && $organizer['contact_email'])
                <a href="tel:{{ $organizer['contact_phone'] }}" class="text-decoration-none small border rounded-pill px-2 py-1">
                    <i class="fas fa-phone me-1"></i>{{ $organizer['contact_phone'] }}
                </a>
            @endif
            @if($organizer['address'])
                <span class="small border rounded-pill px-2 py-1"><i class="fas fa-location-dot me-1"></i>{{ $organizer['address'] }}</span>
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
    <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded-3" style="background:rgba(120,128,150,0.06); border:1px solid rgba(120,128,150,0.18);">
        @if($host->avatar)
            <img src="{{ $host->avatar }}" alt="" class="rounded-circle flex-shrink-0" style="width:44px;height:44px;object-fit:cover;box-shadow:0 0 0 3px rgba(61,107,255,0.12),0 0 0 1px rgba(61,107,255,0.4);">
        @else
            <img src="{{ asset('images/events/host-avatar-placeholder.svg') }}" alt="" class="rounded-circle flex-shrink-0" style="width:44px;height:44px;object-fit:cover;box-shadow:0 0 0 3px rgba(61,107,255,0.12),0 0 0 1px rgba(61,107,255,0.4);">
        @endif
        <div>
            <div class="text-muted fw-semibold" style="font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;">Hosted by</div>
            @if($host->handle)
                <a href="{{ route('creator-profile.show', $host->handle) }}" class="fw-bold text-dark text-decoration-none" style="font-size:14px;">{{ $host->name }}</a>
            @else
                <div class="fw-bold text-dark" style="font-size:14px;">{{ $host->name }}</div>
            @endif
        </div>
    </div>
@endif
