{{--
    One product-shot slot on the marketing home page.

        <x-marketing.shot name="dashboard" alt="The Sayzio dashboard" />

    When an admin has pasted a URL into the matching field on the Marketing
    Settings page, that image renders. When the field is blank, which is the
    default, the drawn mock UI renders instead. That default matters: it means
    a deploy can never put a real customer account on the marketing site, and
    the page still looks finished before anyone has uploaded anything.

    An unknown name renders nothing rather than guessing at a partial.
--}}
@props(['name' => 'dashboard', 'alt' => ''])
@php
    $szSlot = (string) $name;
    $szKnown = array_key_exists($szSlot, \App\Modules\Common\Support\HomeShots::KEYS);
    $szUrl = $szKnown ? \App\Modules\Common\Support\HomeShots::url($szSlot) : null;
@endphp
@if ($szKnown)
    @if ($szUrl !== null)
        <img src="{{ $szUrl }}" alt="{{ $alt }}" loading="lazy" decoding="async"
             {{ $attributes->merge(['class' => 'sz-shot w-full h-auto block']) }}>
    @else
        <x-marketing.shots.styles />
        <span {{ $attributes->merge(['class' => 'sz-shot block']) }}>
            @include('components.marketing.shots.' . $szSlot)
        </span>
    @endif
@endif
