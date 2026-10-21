{{--
    Step two of Create Link: the per-type setup form.

    All twelve type pages hand-rolled their own header -- a back arrow, an
    <h1>, and the Choose/Set up step row -- which is why none of them wore
    the page-hero card every other page in the product wears. Ten were
    byte-identical copies of each other; two (text, updates) had drifted to
    a different pattern entirely, with breadcrumbs instead of the step row.

    One header, twelve callers:

        @include('user.partials.create-step-header', [
            'title'    => 'Short Link',
            'icon'     => 'fa-link',
            'subtitle' => null,              // optional one-liner
        ])

    The step state goes in the hero's existing chip slot rather than its
    subtitle, because the subtitle is escaped and the old markup was HTML.
    "Change type" is a hero action as well as the back arrow: the arrow
    alone is the same destination but far less discoverable, and that link
    is how someone recovers from picking the wrong type.

    `$prefillAlias` is read from the including page when present -- an
    alias typed on the picker must survive the round trip back to it.
--}}
@php
    $__csAlias  = $prefillAlias ?? null;
    $__csPicker = route('user.links.create')
        . (!empty($__csAlias) ? '?alias=' . urlencode($__csAlias) : '');
@endphp

@include('user.partials.page-hero', [
    'title'      => $title ?? 'Create',
    'subtitle'   => $subtitle ?? null,
    'icon'       => $icon ?? 'fa-plus',
    'emblemIcon' => $icon ?? 'fa-plus',
    'favicon'    => null,
    'url'        => null,
    'back'       => $__csPicker,
    'chips'      => [
        ['icon' => 'fa-check', 'text' => 'Choose'],
        ['text' => 'Step 2 of 2 — Set up'],
    ],
    'actions'    => [
        ['label' => 'Change type', 'url' => $__csPicker, 'icon' => 'fa-arrows-rotate', 'class' => 'btn-ghost'],
    ],
])
