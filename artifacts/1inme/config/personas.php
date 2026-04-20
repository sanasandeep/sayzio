<?php

// Single source of truth for the onboarding persona list. Every place
// that renders a persona tile, dropdown, or admin tag picker reads from
// this file so labels/icons stay in sync.
//
// Slugs are stored on `users.persona` and inside the `recommended_personas`
// JSON column on `page_templates`. Keep them stable — renaming a slug
// orphans existing recommendations.

return [
    'list' => [
        ['slug' => 'creator',        'label' => 'Creator',         'icon' => 'fa-star',          'blurb' => 'Content, fans, all your links in one place.'],
        ['slug' => 'artist',         'label' => 'Artist / Designer', 'icon' => 'fa-palette',     'blurb' => 'Showcase your portfolio and shop.'],
        ['slug' => 'writer',         'label' => 'Writer',          'icon' => 'fa-pen-nib',       'blurb' => 'Newsletter, books, articles, and essays.'],
        ['slug' => 'musician',       'label' => 'Musician',        'icon' => 'fa-music',         'blurb' => 'Streams, tour dates, and merch.'],
        ['slug' => 'influencer',     'label' => 'Influencer',      'icon' => 'fa-bolt',          'blurb' => 'Socials, brand deals, and collabs.'],
        ['slug' => 'coach',          'label' => 'Coach / Educator', 'icon' => 'fa-chalkboard-teacher', 'blurb' => 'Courses, bookings, and resources.'],
        ['slug' => 'business',       'label' => 'Business / Brand', 'icon' => 'fa-briefcase',    'blurb' => 'Products, services, and contact info.'],
        ['slug' => 'developer',      'label' => 'Developer',       'icon' => 'fa-code',          'blurb' => 'Projects, repos, and a hire-me pitch.'],
        ['slug' => 'photographer',   'label' => 'Photographer',    'icon' => 'fa-camera',        'blurb' => 'Galleries, prints, and bookings.'],
        ['slug' => 'other',          'label' => 'Something else',  'icon' => 'fa-circle-question', 'blurb' => 'A general-purpose page to start with.'],
    ],
];
