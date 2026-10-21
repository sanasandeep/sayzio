{{--
    One row of people on an event -- under an agenda item, or as the
    event's own line-up when `full` is set.

    Everything here goes through the person's display* methods, never
    the columns directly. Those resolve to the live Contact when one is
    linked and to the stored snapshot otherwise, and this partial does
    not need to know which -- see EventPerson::resolved() for the policy
    and for what it costs a past event.

    Expects:
      people : Collection<EventPerson>, already in billing order
      full   : bool  -- photos and bios, rather than a compact line
--}}
@php
    $full = $full ?? false;
@endphp

@if($people->isNotEmpty())
    <div class="event-people-row {{ $full ? 'd-flex flex-column gap-3 mt-1' : 'd-flex flex-wrap gap-2 mt-2' }}">
        @foreach($people as $person)
            @php
                $billing  = $person->billing();
                $photo    = $person->displayPhotoUrl();
                $name     = $person->displayName();
                $linkedin = $person->displayLinkedinUrl();
                $bio      = $person->displayBio();
            @endphp

            @if($full)
                <div class="event-person d-flex gap-3 align-items-start">
                    @if($photo)
                        <img src="{{ $photo }}" alt=""
                             class="rounded-circle flex-shrink-0"
                             style="width:48px;height:48px;object-fit:cover;">
                    @else
                        {{-- No photo is the common case for a one-off guest;
                             an initial reads better than a broken image. --}}
                        <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center fw-semibold"
                             style="width:48px;height:48px;background:rgba(109,95,255,0.12);color:var(--c-primary,#6d5fff);">
                            {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold" style="color:var(--text-primary,#f8fafc);">{{ $name }}</span>
                            <span class="badge rounded-pill"
                                  style="background:rgba(109,95,255,0.12);color:var(--c-primary,#6d5fff);font-weight:600;">
                                {{ $person->roleLabel() }}
                            </span>
                            @if($linkedin)
                                <a href="{{ $linkedin }}" target="_blank" rel="noopener nofollow"
                                   class="small text-decoration-none" aria-label="{{ $name }} on LinkedIn">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                            @endif
                        </div>
                        @if($billing)
                            <div class="small" style="color:var(--text-muted,#94a3b8);">{{ $billing }}</div>
                        @endif
                        @if($bio)
                            <div class="small mt-1" style="color:var(--text-muted,#94a3b8);white-space:pre-line;">{{ $bio }}</div>
                        @endif
                    </div>
                </div>
            @else
                <span class="event-person-chip d-inline-flex align-items-center gap-2 small"
                      style="background:rgba(148,163,184,0.10);border-radius:999px;padding:4px 10px;">
                    @if($photo)
                        <img src="{{ $photo }}" alt="" class="rounded-circle" style="width:20px;height:20px;object-fit:cover;">
                    @endif
                    <span class="fw-semibold" style="color:var(--text-primary,#f8fafc);">{{ $name }}</span>
                    @if($billing)
                        <span style="color:var(--text-muted,#94a3b8);">· {{ $billing }}</span>
                    @endif
                    <span style="color:var(--c-primary,#6d5fff);font-weight:600;">{{ $person->roleLabel() }}</span>
                </span>
            @endif
        @endforeach
    </div>
@endif
