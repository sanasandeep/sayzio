<?php

// Single source of truth for the onboarding persona list. Every place
// that renders a persona tile, dropdown, or admin tag picker reads from
// this file so labels/icons stay in sync.
//
// Slugs are stored on `users.persona` and inside the `recommended_personas`
// JSON column on `page_templates`. Keep them stable — renaming a slug
// orphans existing recommendations.
//
// `image` is a cover photo used by the image-led onboarding picker; if
// it ever fails to load the UI falls back to the FontAwesome `icon`.
// Images are hosted on picsum.photos with a per-slug seed so they're
// stable across deploys. Admins can replace per-template thumbnails
// individually from the admin Templates UI.

$cover = fn(string $slug) => "https://picsum.photos/seed/persona-{$slug}/600/400";

return [
    'list' => [
        // The original 10 — slugs preserved so existing users.persona values keep resolving.
        ['slug' => 'creator',        'label' => 'Creator',          'icon' => 'fa-star',                'image' => $cover('creator'),        'group' => 'Creators',  'blurb' => 'Content, fans, all your links in one place.'],
        ['slug' => 'artist',         'label' => 'Artist / Designer','icon' => 'fa-palette',             'image' => $cover('artist'),         'group' => 'Creators',  'blurb' => 'Showcase your portfolio and shop.'],
        ['slug' => 'writer',         'label' => 'Writer',           'icon' => 'fa-pen-nib',             'image' => $cover('writer'),         'group' => 'Creators',  'blurb' => 'Newsletter, books, articles, and essays.'],
        ['slug' => 'musician',       'label' => 'Musician',         'icon' => 'fa-music',               'image' => $cover('musician'),       'group' => 'Music',     'blurb' => 'Streams, tour dates, and merch.'],
        ['slug' => 'influencer',     'label' => 'Influencer',       'icon' => 'fa-bolt',                'image' => $cover('influencer'),     'group' => 'Creators',  'blurb' => 'Socials, brand deals, and collabs.'],
        ['slug' => 'coach',          'label' => 'Coach / Educator', 'icon' => 'fa-chalkboard-teacher',  'image' => $cover('coach'),          'group' => 'Services',  'blurb' => 'Courses, bookings, and resources.'],
        ['slug' => 'business',       'label' => 'Business / Brand', 'icon' => 'fa-briefcase',           'image' => $cover('business'),       'group' => 'Business',  'blurb' => 'Products, services, and contact info.'],
        ['slug' => 'developer',      'label' => 'Developer',        'icon' => 'fa-code',                'image' => $cover('developer'),      'group' => 'Creators',  'blurb' => 'Projects, repos, and a hire-me pitch.'],
        ['slug' => 'photographer',   'label' => 'Photographer',     'icon' => 'fa-camera',              'image' => $cover('photographer'),   'group' => 'Creators',  'blurb' => 'Galleries, prints, and bookings.'],

        // New: media & video creators.
        ['slug' => 'podcaster',      'label' => 'Podcaster',        'icon' => 'fa-microphone',          'image' => $cover('podcaster'),      'group' => 'Creators',  'blurb' => 'Episodes, guests, and listen links.'],
        ['slug' => 'filmmaker',      'label' => 'Filmmaker',        'icon' => 'fa-video',               'image' => $cover('filmmaker'),      'group' => 'Creators',  'blurb' => 'Reels, films, and production credits.'],
        ['slug' => 'youtuber',       'label' => 'YouTuber',         'icon' => 'fa-youtube',             'image' => $cover('youtuber'),       'group' => 'Creators',  'blurb' => 'Latest videos, subscribe, sponsors.'],
        ['slug' => 'streamer',       'label' => 'Streamer / Gamer', 'icon' => 'fa-gamepad',             'image' => $cover('streamer'),       'group' => 'Creators',  'blurb' => 'Live schedule, clips, and Discord.'],
        ['slug' => 'author',         'label' => 'Author',           'icon' => 'fa-book',                'image' => $cover('author'),         'group' => 'Creators',  'blurb' => 'Books, signings, and reader list.'],
        ['slug' => 'journalist',     'label' => 'Journalist',       'icon' => 'fa-newspaper',           'image' => $cover('journalist'),     'group' => 'Creators',  'blurb' => 'Latest stories, beats, and contact.'],

        // New: food, fitness & wellness.
        ['slug' => 'chef',           'label' => 'Chef / Food Creator','icon' => 'fa-utensils',          'image' => $cover('chef'),           'group' => 'Food',      'blurb' => 'Recipes, menus, and bookings.'],
        ['slug' => 'fitness',        'label' => 'Fitness Coach',    'icon' => 'fa-dumbbell',            'image' => $cover('fitness'),        'group' => 'Wellness',  'blurb' => 'Programs, plans, and free trials.'],
        ['slug' => 'yoga',           'label' => 'Yoga / Wellness',  'icon' => 'fa-spa',                 'image' => $cover('yoga'),           'group' => 'Wellness',  'blurb' => 'Classes, retreats, and meditations.'],
        ['slug' => 'nutritionist',   'label' => 'Nutritionist',     'icon' => 'fa-apple-alt',           'image' => $cover('nutritionist'),   'group' => 'Wellness',  'blurb' => 'Meal plans, consults, and tips.'],
        ['slug' => 'therapist',      'label' => 'Therapist / Counselor','icon' => 'fa-heart',           'image' => $cover('therapist'),      'group' => 'Wellness',  'blurb' => 'Sessions, intake forms, and resources.'],

        // New: services & professionals.
        ['slug' => 'realestate',     'label' => 'Real Estate Agent','icon' => 'fa-home',                'image' => $cover('realestate'),     'group' => 'Services',  'blurb' => 'Listings, tours, and contact.'],
        ['slug' => 'consultant',     'label' => 'Consultant',       'icon' => 'fa-user-tie',            'image' => $cover('consultant'),     'group' => 'Services',  'blurb' => 'Services, case studies, and inquiries.'],
        ['slug' => 'freelancer',     'label' => 'Freelancer',       'icon' => 'fa-laptop',              'image' => $cover('freelancer'),     'group' => 'Services',  'blurb' => 'Portfolio, rates, and hire-me link.'],
        ['slug' => 'agency',         'label' => 'Agency',           'icon' => 'fa-building',            'image' => $cover('agency'),         'group' => 'Business',  'blurb' => 'Team, work, and new-business form.'],

        // New: hospitality & local.
        ['slug' => 'restaurant',     'label' => 'Restaurant',       'icon' => 'fa-utensils',            'image' => $cover('restaurant'),     'group' => 'Local',     'blurb' => 'Menu, hours, and reservations.'],
        ['slug' => 'cafe',           'label' => 'Cafe / Bar',       'icon' => 'fa-mug-hot',             'image' => $cover('cafe'),           'group' => 'Local',     'blurb' => 'Drinks, events, and directions.'],
        ['slug' => 'event',          'label' => 'Event / Wedding',  'icon' => 'fa-calendar-day',        'image' => $cover('event'),          'group' => 'Local',     'blurb' => 'Date, RSVP, and details.'],

        // New: community & faith.
        ['slug' => 'nonprofit',      'label' => 'Nonprofit',        'icon' => 'fa-hand-holding-heart',  'image' => $cover('nonprofit'),      'group' => 'Community', 'blurb' => 'Mission, donate, and volunteer.'],
        ['slug' => 'church',         'label' => 'Church / Faith',   'icon' => 'fa-church',              'image' => $cover('church'),         'group' => 'Community', 'blurb' => 'Services, sermons, and giving.'],
        ['slug' => 'community',      'label' => 'Community / Club', 'icon' => 'fa-users',               'image' => $cover('community'),      'group' => 'Community', 'blurb' => 'Members, events, and join links.'],

        // New: music & performance.
        ['slug' => 'dj',             'label' => 'DJ',               'icon' => 'fa-headphones',          'image' => $cover('dj'),             'group' => 'Music',     'blurb' => 'Sets, gigs, and bookings.'],
        ['slug' => 'band',           'label' => 'Band',             'icon' => 'fa-guitar',              'image' => $cover('band'),           'group' => 'Music',     'blurb' => 'Tour, tracks, and merch.'],

        // New: lifestyle & beauty.
        ['slug' => 'model',          'label' => 'Model',            'icon' => 'fa-id-badge',            'image' => $cover('model'),          'group' => 'Lifestyle', 'blurb' => 'Portfolio, agency, and bookings.'],
        ['slug' => 'beauty',         'label' => 'Beauty / Makeup',  'icon' => 'fa-magic',               'image' => $cover('beauty'),         'group' => 'Lifestyle', 'blurb' => 'Looks, services, and product picks.'],
        ['slug' => 'fashion',        'label' => 'Fashion',          'icon' => 'fa-tshirt',              'image' => $cover('fashion'),        'group' => 'Lifestyle', 'blurb' => 'Lookbooks, drops, and shop links.'],
        ['slug' => 'travel',         'label' => 'Travel Creator',   'icon' => 'fa-plane',               'image' => $cover('travel'),         'group' => 'Lifestyle', 'blurb' => 'Destinations, guides, and partners.'],

        // New: personal services.
        ['slug' => 'tattoo',         'label' => 'Tattoo Artist',    'icon' => 'fa-paint-brush',         'image' => $cover('tattoo'),         'group' => 'Services',  'blurb' => 'Flash, gallery, and booking form.'],
        ['slug' => 'barber',         'label' => 'Barber / Salon',   'icon' => 'fa-cut',                 'image' => $cover('barber'),         'group' => 'Services',  'blurb' => 'Services, prices, and book online.'],
        ['slug' => 'trainer',        'label' => 'Personal Trainer', 'icon' => 'fa-running',             'image' => $cover('trainer'),        'group' => 'Wellness',  'blurb' => '1:1 plans, classes, and free intro.'],

        // Misc + fallback.
        ['slug' => 'student',        'label' => 'Student',          'icon' => 'fa-graduation-cap',      'image' => $cover('student'),        'group' => 'Other',     'blurb' => 'Resume, projects, and contact.'],
        ['slug' => 'other',          'label' => 'Something else',   'icon' => 'fa-circle-question',     'image' => $cover('other'),          'group' => 'Other',     'blurb' => 'A general-purpose page to start with.'],
    ],
];
