{{--
    One link type, expanded. Fetched over AJAX by the card's expand control,
    so none of this weighs on the initial page: eighteen of these would be
    eighteen iframes nobody asked for.

    The preview is the live demo page for this type, in an iframe, rather
    than a picture of one. It is the real thing, it cannot go stale when the
    product moves, and there is no screenshot to re-shoot. Types without a
    published demo fall back to a still frame of their own drawing.
--}}
@php
    $ltColor = (string) ($type['color'] ?? '#3d6bff');
    $ltName  = (string) ($type['name'] ?? '');
@endphp
<div class="ltm">
    <div class="ltm-side">
        <span class="ltm-ico" style="background:{{ $ltColor }}">
            <i class="fas {{ $type['icon'] ?? 'fa-link' }}" aria-hidden="true"></i>
        </span>

        <h3 class="ltm-name">
            {{ $ltName }}
            @if(!empty($type['new']))
                <span class="ltm-new" style="color:{{ $ltColor }};border-color:{{ $ltColor }}55">New</span>
            @endif
        </h3>

        <p class="ltm-desc">{{ $type['desc'] ?? '' }}</p>

        @if($usage !== '')
            <p class="ltm-usage">
                <span>How you would use it</span>
                {{ $usage }}
            </p>
        @endif

        <div class="ltm-actions">
            <button type="button" class="ltm-cta" style="background:{{ $ltColor }}"
                    onclick="window.trackMarketingEvent&&window.trackMarketingEvent('home_link_type_modal','{{ addslashes($ltName) }}');window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))">
                Get started free <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
            @if($demoUrl)
                <a class="ltm-link" href="{{ $demoUrl }}" target="_blank" rel="noopener">
                    Open the demo <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    </div>

    <div class="ltm-preview">
        {{-- An animated scene built from interface parts, not an iframe. The
             iframe framed the type's EXPLAINER page — a heading, a paragraph
             and a bullet list — so the panel for "Forms" showed prose about
             forms rather than a form. This shows the interface instead. --}}
        @include('home.partials.link-type-scene', [
            'ltSlug'  => \Illuminate\Support\Str::slug($ltName),
            'ltColor' => $ltColor,
        ])
        <p class="ltm-cap">An illustration of the interface, not a screenshot.</p>
    </div>
</div>
