{{-- ============================ HERO ============================ --}}
@php
    // Shared category-tagged thumbnail pool, reused across roles.
    $galleryPool = [
        ['src' => '/images/hero-roles/thumb_youtube.jpg', 'category' => 'Video',   'alt' => 'Latest video'],
        ['src' => '/images/hero-roles/thumb_artwork.jpg', 'category' => 'Art',     'alt' => 'Artwork'],
        ['src' => '/images/hero-roles/thumb_album.jpg',   'category' => 'Music',   'alt' => 'Album cover'],
        ['src' => '/images/hero-roles/thumb_merch.jpg',   'category' => 'Merch',   'alt' => 'Merch drop'],
        ['src' => '/images/hero-roles/thumb_photo.jpg',   'category' => 'Photo',   'alt' => 'Photo print'],
        ['src' => '/images/hero-roles/thumb_podcast.jpg', 'category' => 'Podcast', 'alt' => 'Podcast cover'],
        ['src' => '/images/hero-roles/thumb_writing.jpg', 'category' => 'Writing', 'alt' => 'Latest essay'],
        ['src' => '/images/hero-roles/thumb_food.jpg',    'category' => 'Food',    'alt' => 'Recipe of the week'],
        ['src' => '/images/hero-roles/thumb_fitness.jpg', 'category' => 'Fitness', 'alt' => 'Workout plan'],
        ['src' => '/images/hero-roles/thumb_design.jpg',  'category' => 'Design',  'alt' => 'Design case study'],
        ['src' => '/images/hero-roles/thumb_code.jpg',    'category' => 'Code',    'alt' => 'Open source project'],
        ['src' => '/images/hero-roles/thumb_stream.jpg',  'category' => 'Stream',  'alt' => 'Live stream'],
        ['src' => '/images/hero-roles/thumb_course.jpg',  'category' => 'Course',  'alt' => 'Online course'],
        ['src' => '/images/hero-roles/thumb_book.jpg',    'category' => 'Book',    'alt' => 'Latest book'],
        ['src' => '/images/hero-roles/thumb_travel.jpg',  'category' => 'Travel',  'alt' => 'Travel guide'],
    ];

    $heroRoles = [
        [
            'word' => 'Creator',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#7c3aed 0%,#e94e8c 60%,#ff8a3c 100%)',
            'tint' => '#7c3aed',
            'categories' => ['Video','Merch','Photo','Music','Art','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_creator.jpg', 'handle' => '@jamie.creates', 'tag' => 'Storyteller · 24.1k followers', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-video',             'color' => '#ff5252', 'title' => 'Latest video',       'sub' => 'New drop · 2 days ago',   'thumb' => '/images/hero-roles/thumb_youtube.jpg'],
                ['icon' => 'fas fa-envelope-open-text','color' => '#7c3aed', 'title' => 'Join the newsletter','sub' => 'Weekly · 12k subs'],
                ['icon' => 'fas fa-store',             'color' => '#ff8a3c', 'title' => 'Shop merch',         'sub' => 'New tees in stock',       'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Artist',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#e94e8c 0%,#ff8a3c 55%,#ffc845 100%)',
            'tint' => '#e94e8c',
            'categories' => ['Art','Photo','Merch','Music','Video','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_artist.jpg', 'handle' => '@aria.studio', 'tag' => 'Mixed-media artist · Berlin', 'socials' => ['fa-instagram','fa-pinterest','fa-behance','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-images',             'color' => '#e94e8c', 'title' => 'Latest collection', 'sub' => 'Petals & Concrete · 12 pcs', 'thumb' => '/images/hero-roles/thumb_artwork.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Studio playlist',   'sub' => '4hr ambient mix'],
                ['icon' => 'fas fa-hand-holding-heart', 'color' => '#ff8a3c', 'title' => 'Tip jar',           'sub' => 'Buy me a coffee'],
            ],
        ],
        [
            'word' => 'Businessman',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#0f172a 0%,#1bd4d9 60%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Video','Podcast','Merch','Art','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_business.jpg', 'handle' => '@marcus.solutions', 'tag' => 'Founder · B2B Consulting', 'socials' => ['fa-linkedin','fa-x-twitter','fa-medium','fa-youtube']],
            'blocks' => [
                ['icon' => 'fas fa-concierge-bell', 'color' => '#7c3aed', 'title' => 'Services & pricing', 'sub' => 'Strategy · Audits · Retainers'],
                ['icon' => 'fas fa-calendar-check', 'color' => '#1bd4d9', 'title' => 'Book a call',        'sub' => '30 min · Calendly'],
                ['icon' => 'fas fa-paper-plane',    'color' => '#ff8a3c', 'title' => 'Get a quote',        'sub' => 'Reply within 24h'],
            ],
        ],
        [
            'word' => 'Musician',
            'theme' => 'music',
            'wallpaper' => 'linear-gradient(140deg,#0f3a2a 0%,#1ed760 55%,#1bd4d9 100%)',
            'tint' => '#1ed760',
            'categories' => ['Music','Merch','Video','Podcast','Art','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_musician.jpg', 'handle' => '@luna.live', 'tag' => 'Indie pop · New EP out now', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-instagram']],
            'blocks' => [
                ['icon' => 'fab fa-spotify',     'color' => '#1ed760', 'title' => 'New EP — Saltwater', 'sub' => '5 tracks · Listen now', 'thumb' => '/images/hero-roles/thumb_album.jpg'],
                ['icon' => 'fas fa-ticket-alt',  'color' => '#e94e8c', 'title' => 'Tour 2026',          'sub' => '12 cities · Tickets live'],
                ['icon' => 'fas fa-store',       'color' => '#ffc845', 'title' => 'Vinyl & tees',       'sub' => 'Limited drop',          'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Coach',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#1bd4d9 0%,#7c3aed 60%,#ffc845 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Video','Photo','Podcast','Music','Merch','Art'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_coach.jpg', 'handle' => '@coach.kai', 'tag' => 'Strength coach · 1:1 + group', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-spotify']],
            'blocks' => [
                ['icon' => 'fas fa-calendar-check',  'color' => '#1bd4d9', 'title' => 'Book a session',     'sub' => '45 min consult'],
                ['icon' => 'fas fa-quote-right',     'color' => '#7c3aed', 'title' => 'Wins from clients',  'sub' => '140+ five-star reviews'],
                ['icon' => 'fas fa-clipboard-list',  'color' => '#ff8a3c', 'title' => 'Free intake form',   'sub' => '2 minutes · No fluff'],
            ],
        ],
        [
            'word' => 'Photographer',
            'theme' => 'portfolio',
            'wallpaper' => 'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Art','Merch','Video','Music','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_photographer.jpg', 'handle' => '@iris.frames', 'tag' => 'Travel & landscape · Iceland', 'socials' => ['fa-instagram','fa-pinterest','fa-flickr','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-th',            'color' => '#1bd4d9', 'title' => 'Portfolio · 2026', 'sub' => '48 photos',         'thumb' => '/images/hero-roles/thumb_photo.jpg'],
                ['icon' => 'fas fa-shopping-bag',  'color' => '#ff8a3c', 'title' => 'Print shop',       'sub' => 'A2 / A3 / canvas'],
                ['icon' => 'fas fa-paper-plane',   'color' => '#e94e8c', 'title' => 'Hire me',          'sub' => 'Weddings · Brand'],
            ],
        ],
        [
            'word' => 'Influencer',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#e94e8c 0%,#7c3aed 50%,#ffc845 100%)',
            'tint' => '#e94e8c',
            'categories' => ['Video','Photo','Merch','Music','Art','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_influencer.jpg', 'handle' => '@maya.daily', 'tag' => 'Lifestyle · 480k across socials', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-snapchat']],
            'blocks' => [
                ['icon' => 'fab fa-instagram', 'color' => '#e94e8c', 'title' => 'Latest reel',     'sub' => 'Spring haul'],
                ['icon' => 'fab fa-tiktok',    'color' => '#1bd4d9', 'title' => 'Trending today',  'sub' => '2.1M views'],
                ['icon' => 'fas fa-handshake', 'color' => '#ffc845', 'title' => 'Brand deals',     'sub' => 'Press kit · Rates'],
            ],
        ],
        [
            'word' => 'Podcaster',
            'theme' => 'podcast',
            'wallpaper' => 'linear-gradient(140deg,#ff8a3c 0%,#e94e8c 50%,#7c3aed 100%)',
            'tint' => '#ff8a3c',
            'categories' => ['Podcast','Music','Video','Art','Merch','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_podcaster.jpg', 'handle' => '@theo.talks', 'tag' => 'Weekly tech & culture', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-apple',              'color' => '#ffffff', 'title' => 'Apple Podcasts',  'sub' => 'Ep. 87 · 42 min',     'thumb' => '/images/hero-roles/thumb_podcast.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Spotify',         'sub' => 'Subscribe · 18k listeners'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#ff8a3c', 'title' => 'Show notes',      'sub' => 'Newsletter every Friday'],
            ],
        ],
        [
            'word' => 'Writer',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#1e1b4b 0%,#7c3aed 55%,#ec4899 100%)',
            'tint' => '#a855f7',
            'categories' => ['Writing','Book','Podcast','Video','Art','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_writer.jpg', 'handle' => '@nora.writes', 'tag' => 'Essayist · Substack 18k', 'socials' => ['fa-substack','fa-medium','fa-x-twitter','fa-instagram']],
            'blocks' => [
                ['icon' => 'fas fa-feather',            'color' => '#a855f7', 'title' => 'New essay',         'sub' => 'On slow internet · 12 min', 'thumb' => '/images/hero-roles/thumb_writing.jpg'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#7c3aed', 'title' => 'Subscribe free',    'sub' => 'Weekly long reads'],
                ['icon' => 'fas fa-book-open',          'color' => '#ffc845', 'title' => 'Buy the book',      'sub' => 'Quiet Signals · paperback', 'thumb' => '/images/hero-roles/thumb_book.jpg'],
            ],
        ],
        [
            'word' => 'Chef',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#7c2d12 0%,#fb923c 55%,#fde047 100%)',
            'tint' => '#fb923c',
            'categories' => ['Food','Video','Photo','Course','Merch','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_chef.jpg', 'handle' => '@chef.remi', 'tag' => 'Recipes · Pop-ups · Cookbook', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-pinterest']],
            'blocks' => [
                ['icon' => 'fas fa-utensils',           'color' => '#fb923c', 'title' => 'Recipe of the week', 'sub' => '20-min weeknight pasta',  'thumb' => '/images/hero-roles/thumb_food.jpg'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#7c3aed', 'title' => 'Knife skills course','sub' => '6 lessons · self-paced',  'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-store',              'color' => '#ff8a3c', 'title' => 'Shop the spice kit', 'sub' => 'Limited drop',           'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Yogi',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#064e3b 0%,#10b981 55%,#fde047 100%)',
            'tint' => '#10b981',
            'categories' => ['Fitness','Video','Course','Podcast','Photo','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_fitness.jpg', 'handle' => '@yoga.with.sage', 'tag' => 'Yoga & breathwork · Online + Bali', 'socials' => ['fa-youtube','fa-instagram','fa-spotify','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-dumbbell',           'color' => '#10b981', 'title' => '30-day flow',        'sub' => 'Daily 20-min sessions',   'thumb' => '/images/hero-roles/thumb_fitness.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#1bd4d9', 'title' => 'Book a 1:1',         'sub' => '60 min · Zoom or Bali'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#7c3aed', 'title' => 'Teacher training',   'sub' => '200hr · Cohort 6 open',   'thumb' => '/images/hero-roles/thumb_course.jpg'],
            ],
        ],
        [
            'word' => 'Designer',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#312e81 0%,#ec4899 55%,#fbbf24 100%)',
            'tint' => '#ec4899',
            'categories' => ['Design','Art','Photo','Merch','Video','Course'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_designer.jpg', 'handle' => '@studio.kova', 'tag' => 'Brand & product designer · Lisbon', 'socials' => ['fa-dribbble','fa-behance','fa-instagram','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-pen-ruler',          'color' => '#ec4899', 'title' => 'Selected work',     'sub' => '14 case studies',         'thumb' => '/images/hero-roles/thumb_design.jpg'],
                ['icon' => 'fas fa-paper-plane',        'color' => '#7c3aed', 'title' => 'Hire the studio',   'sub' => 'Brand · Web · Product'],
                ['icon' => 'fas fa-store',              'color' => '#ffc845', 'title' => 'Template shop',     'sub' => 'Figma kits · ready to ship'],
            ],
        ],
        [
            'word' => 'Developer',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#0f172a 0%,#1bd4d9 55%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Code','Video','Course','Podcast','Writing','Design'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_developer.jpg', 'handle' => '@dev.with.kai', 'tag' => 'Open source · Indie hacker', 'socials' => ['fa-github','fa-x-twitter','fa-youtube','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-code',               'color' => '#ffffff', 'title' => 'Open source',       'sub' => '8.4k ★ · TypeScript',     'thumb' => '/images/hero-roles/thumb_code.jpg'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#1bd4d9', 'title' => 'Build with me',     'sub' => 'Course · 24 lessons',     'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-feather',            'color' => '#7c3aed', 'title' => 'Engineering blog',  'sub' => 'Weekly deep dives',       'thumb' => '/images/hero-roles/thumb_writing.jpg'],
            ],
        ],
        [
            'word' => 'Streamer',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#3b0764 0%,#a855f7 55%,#ec4899 100%)',
            'tint' => '#a855f7',
            'categories' => ['Stream','Video','Merch','Music','Podcast','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_streamer.jpg', 'handle' => '@nyx.plays', 'tag' => 'Variety streamer · 92k Twitch', 'socials' => ['fa-twitch','fa-youtube','fa-discord','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-twitch',             'color' => '#a855f7', 'title' => 'Live now',          'sub' => 'Speedrun night · 1.2k watching', 'thumb' => '/images/hero-roles/thumb_stream.jpg'],
                ['icon' => 'fab fa-discord',            'color' => '#5865f2', 'title' => 'Join the Discord',  'sub' => '14k members'],
                ['icon' => 'fas fa-store',              'color' => '#ffc845', 'title' => 'Merch · Hoodies',   'sub' => 'New season drop',         'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Educator',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#0c4a6e 0%,#38bdf8 55%,#7c3aed 100%)',
            'tint' => '#38bdf8',
            'categories' => ['Course','Video','Writing','Podcast','Book','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_educator.jpg', 'handle' => '@ms.alvarez', 'tag' => 'Tutor · SAT · Calculus · 1:1', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-graduation-cap',     'color' => '#38bdf8', 'title' => 'Live cohort',       'sub' => 'Spring intake open',      'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#7c3aed', 'title' => 'Book a session',    'sub' => '50 min · Zoom'],
                ['icon' => 'fas fa-clipboard-list',     'color' => '#ff8a3c', 'title' => 'Free practice pack','sub' => 'PDFs · Drills · Keys'],
            ],
        ],
        [
            'word' => 'Author',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#451a03 0%,#f59e0b 55%,#ec4899 100%)',
            'tint' => '#f59e0b',
            'categories' => ['Book','Writing','Podcast','Video','Photo','Art'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_author.jpg', 'handle' => '@iain.morrow', 'tag' => 'Novelist · Quiet Signals out now', 'socials' => ['fa-goodreads','fa-instagram','fa-x-twitter','fa-medium']],
            'blocks' => [
                ['icon' => 'fas fa-book-open',          'color' => '#f59e0b', 'title' => 'Buy Quiet Signals', 'sub' => 'Hardcover · audiobook',   'thumb' => '/images/hero-roles/thumb_book.jpg'],
                ['icon' => 'fas fa-feather',            'color' => '#7c3aed', 'title' => 'Read a chapter',    'sub' => 'Free preview'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#1bd4d9', 'title' => 'Tour & signings',   'sub' => '8 cities · Spring'],
            ],
        ],
        [
            'word' => 'Nonprofit',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#064e3b 0%,#22c55e 55%,#1bd4d9 100%)',
            'tint' => '#22c55e',
            'categories' => ['Video','Photo','Writing','Podcast','Merch','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_nonprofit.jpg', 'handle' => '@cleanwave.org', 'tag' => 'Ocean cleanup · 501(c)(3)', 'socials' => ['fa-instagram','fa-youtube','fa-linkedin','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-hand-holding-heart', 'color' => '#22c55e', 'title' => 'Donate today',      'sub' => 'Every $5 = 20 lbs cleaned'],
                ['icon' => 'fas fa-people-group',       'color' => '#1bd4d9', 'title' => 'Volunteer',         'sub' => 'Beach cleanups · monthly'],
                ['icon' => 'fas fa-chart-line',         'color' => '#7c3aed', 'title' => 'Impact report',     'sub' => '2025 · 8.4M lbs removed'],
            ],
        ],
        [
            'word' => 'Realtor',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#ffc845 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Video','Writing','Podcast','Art','Merch'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_realtor.jpg', 'handle' => '@home.with.eli', 'tag' => 'Realtor® · Austin TX · 9 yrs', 'socials' => ['fa-instagram','fa-youtube','fa-linkedin','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-house',              'color' => '#1bd4d9', 'title' => 'Featured listings', 'sub' => '12 active · Austin metro', 'thumb' => '/images/hero-roles/thumb_photo.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#7c3aed', 'title' => 'Book a tour',       'sub' => 'In-person or virtual'],
                ['icon' => 'fas fa-calculator',         'color' => '#ff8a3c', 'title' => 'Free home valuation','sub' => '60-second estimate'],
            ],
        ],
        [
            'word' => 'Traveler',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#0c4a6e 0%,#06b6d4 55%,#fde047 100%)',
            'tint' => '#06b6d4',
            'categories' => ['Travel','Photo','Video','Writing','Podcast','Course'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_travel.jpg', 'handle' => '@wander.with.io', 'tag' => 'Travel · 38 countries · Maps & guides', 'socials' => ['fa-instagram','fa-youtube','fa-tiktok','fa-pinterest']],
            'blocks' => [
                ['icon' => 'fas fa-plane',              'color' => '#06b6d4', 'title' => 'City guides',       'sub' => '12 cities · live maps',   'thumb' => '/images/hero-roles/thumb_travel.jpg'],
                ['icon' => 'fas fa-camera',             'color' => '#ec4899', 'title' => 'Lightroom presets', 'sub' => 'Sun-soaked · Misty pack'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#ffc845', 'title' => 'Trip newsletter',   'sub' => 'Monthly · 24k readers'],
            ],
        ],
    ];

    // Visible block-type icons cluster shown in the hero.
    $heroBlockIcons = [
        ['i' => 'fas fa-store',              'c' => '#ff8a3c', 'l' => 'Merch'],
        ['i' => 'fas fa-link',               'c' => '#1bd4d9', 'l' => 'Link'],
        ['i' => 'fas fa-qrcode',             'c' => '#7c3aed', 'l' => 'QR'],
        ['i' => 'fas fa-music',              'c' => '#e94e8c', 'l' => 'Music'],
        ['i' => 'fas fa-video',              'c' => '#ffc845', 'l' => 'Video'],
        ['i' => 'fas fa-image',              'c' => '#1bd4d9', 'l' => 'Image'],
        ['i' => 'fas fa-microphone',         'c' => '#ff8a3c', 'l' => 'Podcast'],
        ['i' => 'fas fa-calendar-check',     'c' => '#7c3aed', 'l' => 'Calendar'],
        ['i' => 'fas fa-book-open',          'c' => '#f59e0b', 'l' => 'Book'],
        ['i' => 'fas fa-graduation-cap',     'c' => '#38bdf8', 'l' => 'Course'],
        ['i' => 'fas fa-utensils',           'c' => '#fb923c', 'l' => 'Recipe'],
        ['i' => 'fas fa-feather',            'c' => '#a855f7', 'l' => 'Writing'],
        ['i' => 'fas fa-code',               'c' => '#ffffff', 'l' => 'Code'],
        ['i' => 'fas fa-dumbbell',           'c' => '#10b981', 'l' => 'Fitness'],
        ['i' => 'fas fa-plane',              'c' => '#06b6d4', 'l' => 'Travel'],
        ['i' => 'fas fa-house',              'c' => '#1bd4d9', 'l' => 'Listing'],
        ['i' => 'fas fa-hand-holding-heart', 'c' => '#22c55e', 'l' => 'Donate'],
    ];
@endphp

<section class="relative pt-28 pb-20 lg:pt-44 lg:pb-32 xl:pt-52 xl:pb-40 overflow-hidden" aria-labelledby="hero-h">
    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:8%;  bottom:-20vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:18%; bottom:-30vh; animation-delay:-3s"><div class="w-2 h-6 rounded-full" style="background:var(--c3)"></div></div>
    <div class="confetti drift-a" style="left:78%; bottom:-25vh; animation-delay:-6s"><div class="w-4 h-4 rounded-full" style="background:var(--c4)"></div></div>
    <div class="confetti drift-b" style="left:88%; bottom:-15vh; animation-delay:-9s"><div class="w-3 h-3 rotate-45" style="background:var(--c5)"></div></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="hero-grid grid grid-cols-1 gap-y-12 lg:grid-cols-[1.05fr_1fr] lg:gap-x-12 xl:gap-x-16 lg:items-center">
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c1)"></span>
                        <span class="ring-pulse" style="inset:0;background:var(--c1);"></span>
                    </span>
                    <span class="grad-text">Analytics · Followers · Social integrations · Free Forever · Native mobile app</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-8">
                    <span class="block">I am a</span>
                    <span class="relative inline-block min-h-[1.1em]">
                        <span id="hero-role-word" class="grad-text role-word">Creator</span>
                        <svg class="absolute -bottom-3 left-0 w-full" height="14" viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true">
                            <path class="draw-line" d="M2 9 Q 60 2, 110 8 T 218 6" stroke="url(#g)" stroke-width="5" fill="none" stroke-linecap="round"/>
                            <defs><linearGradient id="g"><stop offset="0%" stop-color="#1bd4d9"/><stop offset="50%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#ffc845"/></linearGradient></defs>
                        </svg>
                    </span>
                    <span class="sr-only" aria-live="polite" aria-atomic="true" id="hero-role-sr">Creator</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-10 leading-relaxed">
                    Whoever you are, 1INME is the <strong class="text-white">all-in-one</strong> link, monetization &amp; growth stack: drag-and-drop biolink pages, branded short links, dynamic QR codes, NFC tags, built-in DMs, an AI Performance Coach and a native mobile app — <strong class="text-white">free forever</strong>, no card required.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 justify-center lg:justify-start">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','hero'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                        Make mine free <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#features" class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-300 hover:text-white">
                        See it live <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                </div>

                @php
                    $__trustStripRaw = (array) \App\Modules\Admin\Models\AppSetting::get('marketing_trust_strip', []);
                    $__trustStrip = \App\Modules\Common\Support\SitePagesContent::normalizeTrustStrip($__trustStripRaw);
                    if (empty($__trustStrip)) {
                        $__trustStrip = \App\Modules\Common\Support\SitePagesContent::trustStripDefault();
                    }
                    $__trustColors = ['var(--c1)', 'var(--c3)', 'var(--c5)', 'var(--c2)', 'var(--c4)'];
                @endphp
                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-12 justify-center lg:justify-start text-sm">
                    @foreach($__trustStrip as $i => $__t)
                        <span class="flex items-center gap-2 text-gray-400">
                            <i class="fas {{ $__t['icon'] ?? 'fa-check' }} text-[13px]" style="color: {{ $__trustColors[$i % count($__trustColors)] }}"></i>
                            <span class="font-bold text-white">{{ $__t['value'] ?? '' }}</span>
                            <span class="text-gray-500">{{ $__t['label'] ?? '' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Hero phone mockup + gallery + block icons --}}
            <div class="reveal rd-2 relative stack-scene lg:justify-self-end w-full max-w-[560px] mx-auto" id="hero-phone-scene">
                {{-- Decorative stickers (kept inside the phone column on lg+ so they don't float into the headline area) --}}
                <div class="sticker hidden lg:block top-4 right-6 w-10 h-10 rounded-full wiggle shake-hover opacity-80" style="background:var(--c4)"></div>
                <div class="sticker top-12 right-2 w-8 h-8 rounded-lg spin-slow opacity-70" style="background:var(--c5)"></div>
                <div class="sticker hidden lg:block bottom-32 right-0 w-9 h-9 rounded-2xl wiggle opacity-80" style="background:var(--c1); animation-delay:-1s"></div>
                <div class="sticker top-1/3 -right-3 w-6 h-6 rounded-full wiggle opacity-80" style="background:var(--c3); animation-delay:-2s"></div>

                {{-- Phone mockup --}}
                <div class="relative flex items-center justify-center hero-phone-stage">
                    <div class="hero-phone-frame">
                    <div id="hero-phone-wrap" class="hero-phone-wrap float-c">
                        <div class="hero-phone">
                            <div id="hero-phone-screen" class="hero-phone-screen">
                                <div class="hero-notch"></div>
                                <div id="hero-stack" class="hero-phone-content" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating info cards (desktop only) --}}
                    <div class="float-b float-card float-card--visitors hidden lg:block" aria-hidden="true">
                        <div class="flex items-center justify-between mb-1">
                            <span class="float-card-label">Live visitors</span>
                            <span class="flex items-center gap-1 text-[9px] font-bold" style="color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>NOW</span>
                        </div>
                        <div class="text-xl font-bold" id="hero-tick-visitors" data-tick-visitors>247</div>
                        <svg class="w-full h-6" viewBox="0 0 100 30" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="url(#sl)" stroke-width="2.5" stroke-linecap="round" points="0,22 12,18 24,20 36,12 48,15 60,8 72,11 84,5 100,7"/>
                            <defs><linearGradient id="sl"><stop offset="0%" stop-color="#1bd4d9"/><stop offset="100%" stop-color="#e94e8c"/></linearGradient></defs>
                        </svg>
                    </div>

                    <div class="float-c float-card float-card--coach hidden lg:block" style="animation-delay:-2s" aria-hidden="true">
                        <div class="flex items-center gap-2 mb-1.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center grad-bar"><i class="fas fa-bolt text-white text-xs"></i></div>
                            <div>
                                <span class="float-card-label">Performance Coach</span>
                                <div class="text-xs font-bold">Health score 87</div>
                            </div>
                        </div>
                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full grad-bar rounded-full" style="width:87%"></div>
                        </div>
                    </div>

                    <div class="float-a float-card float-card--toplink hidden lg:block" style="animation-delay:-1s" aria-hidden="true">
                        <div class="flex items-center justify-between mb-1">
                            <span class="float-card-label">Top link</span>
                            <span class="text-[9px] font-bold" style="color:#1ed760">+18%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:rgba(124,58,237,.2);color:#a78bfa"><i class="fas fa-link text-xs"></i></div>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold truncate">Latest drop</div>
                                <div class="text-[9px] text-gray-400">1,284 clicks</div>
                            </div>
                        </div>
                    </div>

                    <div class="float-b float-card float-card--conv hidden lg:block" style="animation-delay:-3.5s" aria-hidden="true">
                        <span class="float-card-label">Conversions today</span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <div class="text-xl font-bold">38</div>
                            <span class="text-[10px] font-bold" style="color:#1ed760">+12%</span>
                        </div>
                        <div class="flex items-end gap-0.5 h-5 mt-1">
                            @foreach([6,9,5,11,8,14,10,16,13,18] as $h)
                                <span class="flex-1 rounded-sm" style="height:{{ $h * 5 }}%;background:linear-gradient(180deg,#1bd4d9,#7c3aed)"></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="float-c float-card float-card--qr hidden lg:block" style="animation-delay:-1.5s" aria-hidden="true">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(124,58,237,.2);color:#a78bfa"><i class="fas fa-qrcode text-base"></i></div>
                            <div>
                                <span class="float-card-label">QR scans</span>
                                <div class="text-sm font-bold leading-tight">1,420 <span class="text-[10px] text-gray-400 font-normal">/ 7d</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="float-a float-card float-card--follower hidden lg:block" style="animation-delay:-2.5s" aria-hidden="true">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[11px] font-bold" style="background:linear-gradient(135deg,#ec4899,#7c3aed)">M</div>
                            <div class="min-w-0">
                                <span class="float-card-label">New follower</span>
                                <div class="text-[11px] font-bold truncate">@maya.daily</div>
                                <div class="text-[9px] text-gray-400">just now</div>
                            </div>
                        </div>
                    </div>

                    <div class="float-b float-card float-card--revenue hidden lg:block" style="animation-delay:-4s" aria-hidden="true">
                        <span class="float-card-label">Revenue today</span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <div class="text-xl font-bold" id="hero-tick-revenue" data-tick-revenue>$ 412</div>
                            <span class="text-[10px] font-bold" style="color:#1ed760">▲ 9%</span>
                        </div>
                        <div class="flex items-center gap-1 mt-1 text-[9px] text-gray-400">
                            <i class="fas fa-store" style="color:#ff8a3c"></i> 6 orders · 2 tips
                        </div>
                    </div>
                    </div>{{-- /hero-phone-frame --}}
                </div>

                {{-- Compact horizontal interactive tile strip (all breakpoints) --}}
                <div class="mt-6">
                    <div class="hero-rail-label text-[10px] font-bold uppercase tracking-[.18em] text-gray-400 text-center lg:text-left mb-2 px-1">
                        Looks like a <span id="hero-rail-role-label" class="grad-text">creator</span> page
                    </div>
                    <div id="hero-tile-rail" class="hero-tile-rail" role="group" aria-label="Choose a profile preview"></div>
                </div>

                {{-- Mobile-only stacked stats row (replaces floating cards on small screens) --}}
                <div class="hero-mobile-stats lg:hidden mt-5" aria-hidden="true">
                    <div class="hero-mstat">
                        <span class="lbl"><span class="w-1.5 h-1.5 rounded-full pulse-dot inline-block mr-1" style="background:var(--c1)"></span>Live</span>
                        <span class="val">247</span>
                        <span class="sub">visitors</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-bolt" style="color:#ffc845"></i> Coach</span>
                        <span class="val">87</span>
                        <span class="sub">health</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-qrcode" style="color:#a78bfa"></i> QR</span>
                        <span class="val">1.4k</span>
                        <span class="sub">scans</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-store" style="color:#ff8a3c"></i> Today</span>
                        <span class="val">$412</span>
                        <span class="sub">revenue</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Floating-card metric tickers: gently increment Live visitors and
        // Revenue today so the hero feels alive. Pauses when off-screen,
        // when the tab is hidden, and respects prefers-reduced-motion.
        (function () {
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) return;
            const visEl = document.getElementById('hero-tick-visitors');
            const revEl = document.getElementById('hero-tick-revenue');
            if (!visEl && !revEl) return;

            const parseNum = (el, fallback) => {
                if (!el) return fallback;
                const n = parseInt((el.textContent || '').replace(/[^0-9]/g, ''), 10);
                return Number.isFinite(n) ? n : fallback;
            };
            let visitors = parseNum(visEl, 247);
            let revenue  = parseNum(revEl, 412);
            let inView = false;
            let timer = null;

            function flash(el) {
                if (!el) return;
                el.style.transition = 'color .25s ease';
                const prev = el.style.color;
                el.style.color = '#1ed760';
                setTimeout(() => { el.style.color = prev; }, 280);
            }

            function tick() {
                if (document.hidden || !inView) return;
                if (visEl && Math.random() < 0.85) {
                    visitors += Math.random() < 0.15 ? -1 : (Math.random() < 0.4 ? 2 : 1);
                    if (visitors < 180) visitors = 180;
                    if (visitors > 320) visitors = 320;
                    visEl.textContent = visitors.toLocaleString();
                    flash(visEl);
                }
                if (revEl && Math.random() < 0.5) {
                    revenue += 1 + Math.floor(Math.random() * 6);
                    revEl.textContent = '$ ' + revenue.toLocaleString();
                    flash(revEl);
                }
            }

            function start() {
                if (timer) return;
                timer = setInterval(tick, 2200);
            }
            function stop() {
                if (!timer) return;
                clearInterval(timer);
                timer = null;
            }

            const target = (visEl || revEl).closest('.hero-phone-stage') || (visEl || revEl);
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        inView = e.isIntersecting;
                        if (inView) start(); else stop();
                    });
                }, { threshold: 0.15 });
                io.observe(target);
            } else {
                inView = true;
                start();
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stop(); else if (inView) start();
            });
        })();

        (function () {
            const ROLES   = @json($heroRoles);
            const word    = document.getElementById('hero-role-word');
            const sr      = document.getElementById('hero-role-sr');
            const stack   = document.getElementById('hero-stack');
            const screen  = document.getElementById('hero-phone-screen');
            const gallery = document.getElementById('hero-gallery');
            const galLbl  = document.getElementById('hero-gallery-label');
            const railLbl = document.getElementById('hero-rail-role-label');
            const phoneWrap = document.getElementById('hero-phone-wrap');
            const phoneScene = document.getElementById('hero-phone-scene');
            const tileRail = document.getElementById('hero-tile-rail');
            if (!word || !stack) return;

            const AUTO_ROTATE_MS = 3000;
            const SWAP_MS        = 220;
            const USER_PAUSE_MS  = 6000;
            let pauseUntil = 0;

            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const isDesktop = window.matchMedia && window.matchMedia('(min-width: 1024px)').matches;
            const escapeHTML = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

            function orderedGallery(role) {
                const items = (role.gallery || []).slice();
                const order = role.categories || [];
                const rank = (cat) => {
                    const i = order.indexOf(cat);
                    return i === -1 ? 999 : i;
                };
                items.sort((a,b) => rank(a.category) - rank(b.category));
                return items;
            }

            function buildGalleryHTML(role) {
                return orderedGallery(role).map((g, i) => `
                    <div class="hero-gallery-item gallery-shimmer" style="--gd:${i * 60}ms">
                        ${pictureThumb(g.src, '', 120, 120, '(max-width: 1023px) 110px, 120px', g.alt || '')}
                        <span class="gallery-cat">${escapeHTML(g.category)}</span>
                    </div>`).join('');
            }

            // A pool of distinct wallpapers. Every role swap picks a
            // fresh one at random (never repeats the previous one) so
            // the phone feels alive — not locked to a single gradient
            // per role. Role's own wallpaper is kept as the seed.
            const WALLPAPERS = [
                'linear-gradient(140deg,#7c3aed 0%,#e94e8c 60%,#ff8a3c 100%)',
                'linear-gradient(140deg,#e94e8c 0%,#ff8a3c 55%,#ffc845 100%)',
                'linear-gradient(140deg,#0f172a 0%,#1bd4d9 60%,#7c3aed 100%)',
                'linear-gradient(140deg,#0f3a2a 0%,#1ed760 55%,#1bd4d9 100%)',
                'linear-gradient(140deg,#1bd4d9 0%,#7c3aed 60%,#ffc845 100%)',
                'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#7c3aed 100%)',
                'linear-gradient(140deg,#e94e8c 0%,#7c3aed 50%,#ffc845 100%)',
                'linear-gradient(140deg,#ff8a3c 0%,#e94e8c 50%,#7c3aed 100%)',
                'linear-gradient(160deg,#0b132b 0%,#3a0ca3 45%,#f72585 100%)',
                'linear-gradient(135deg,#06b6d4 0%,#3b82f6 55%,#9333ea 100%)',
                'linear-gradient(150deg,#fde047 0%,#fb923c 45%,#ef4444 100%)',
                'linear-gradient(135deg,#064e3b 0%,#10b981 50%,#fde047 100%)',
                'linear-gradient(140deg,#312e81 0%,#ec4899 55%,#fbbf24 100%)',
                'linear-gradient(160deg,#1e1b4b 0%,#7c3aed 45%,#22d3ee 100%)',
            ];
            let lastWallpaper = null;
            function applyWallpaper(role) {
                if (!screen) return;
                // Different wallpaper each call. Include the role's
                // own wallpaper as an option but never pick the same
                // value as the previous render. Dedupe by value so a
                // role gradient that also appears in WALLPAPERS can't
                // be re-selected under a different index.
                const pool = Array.from(new Set([role.wallpaper, ...WALLPAPERS].filter(Boolean)));
                let pick;
                do { pick = pool[Math.floor(Math.random() * pool.length)]; }
                while (pool.length > 1 && pick === lastWallpaper);
                lastWallpaper = pick;
                screen.style.background = pick;
            }

            function pickFromGallery(role, category, fallbackIndex) {
                const g = role.gallery || [];
                const hit = g.find(x => x.category === category);
                if (hit) return hit.src;
                return (g[fallbackIndex] || g[0] || {}).src || '';
            }

            // ---- Responsive image helpers (WebP + JPEG fallback) ----
            function heroImgBase(src) {
                // strip leading slash-safe extension; works for /images/hero-roles/foo.jpg
                return (src || '').replace(/\.jpe?g$/i, '');
            }
            // Avatar / role headshot — only ever displayed up to ~120px wide.
            function pictureAvatar(src, cls, w, h) {
                const base = heroImgBase(src);
                const webp = `${base}-200.webp`;
                const jpg  = `${base}-200.jpg`;
                return `<picture>`
                    + `<source type="image/webp" srcset="${escapeHTML(webp)}">`
                    + `<img class="${escapeHTML(cls)}" src="${escapeHTML(jpg)}" alt="" loading="lazy" decoding="async" width="${w}" height="${h}">`
                    + `</picture>`;
            }
            // Thumb / cover / gallery image — displayed anywhere from ~50px to ~280px.
            // opts: { eager: bool } — when true, marks above-the-fold image as eager + high priority.
            function pictureThumb(src, cls, w, h, sizes, alt, opts) {
                const base = heroImgBase(src);
                const altA = escapeHTML(alt || '');
                const sz   = escapeHTML(sizes || '(max-width: 640px) 50vw, 320px');
                const eager = !!(opts && opts.eager);
                const loadAttr = eager ? 'eager' : 'lazy';
                const fpAttr   = eager ? ' fetchpriority="high"' : '';
                return `<picture>`
                    + `<source type="image/webp" srcset="${escapeHTML(base)}-320.webp 320w, ${escapeHTML(base)}-640.webp 640w" sizes="${sz}">`
                    + `<source type="image/jpeg" srcset="${escapeHTML(base)}-320.jpg 320w, ${escapeHTML(base)}-640.jpg 640w" sizes="${sz}">`
                    + `<img class="${escapeHTML(cls)}" src="${escapeHTML(base)}-320.jpg" alt="${altA}" loading="${loadAttr}"${fpAttr} decoding="async" width="${w}" height="${h}">`
                    + `</picture>`;
            }

            // Map category -> Font Awesome icon for tile fallback covers.
            const CAT_ICONS = {
                Video:'fas fa-video', Art:'fas fa-palette', Music:'fas fa-music',
                Merch:'fas fa-store', Photo:'fas fa-camera', Podcast:'fas fa-microphone',
                Writing:'fas fa-feather', Food:'fas fa-utensils', Fitness:'fas fa-dumbbell',
                Design:'fas fa-pen-ruler', Code:'fab fa-github', Stream:'fab fa-twitch',
                Course:'fas fa-graduation-cap', Book:'fas fa-book-open', Travel:'fas fa-plane',
            };
            function fallbackTileCover(role) {
                const cat = (role.categories || [])[0] || '';
                const ico = CAT_ICONS[cat] || 'fas fa-shapes';
                const bg  = role.wallpaper || 'linear-gradient(140deg,#7c3aed,#1bd4d9)';
                return `<span class="hero-tile-fallback" style="background:${bg}">`
                     + `<i class="${ico}" aria-hidden="true"></i>`
                     + `<span class="ftl">${escapeHTML(cat || role.word)}</span>`
                     + `</span>`;
            }

            // Each theme supplies its own bespoke profile block so the
            // profile never looks the same between role swaps. The shared
            // .hp-prof skeleton supplies the glass card frame; per-theme
            // `var-*` classes layer on the unique treatment.
            function profFor(role) {
                const p = role.profile;
                const h = escapeHTML(p.handle);
                const t = escapeHTML(p.tag);
                const av = p.avatar;
                const verified = '<i class="fas fa-circle-check pvd"></i>';
                const avatarImg = pictureAvatar(av, 'pav', 56, 56);

                switch (role.theme) {
                    case 'creator':
                        return `<div class="hp-prof var-creator theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                              <i class="fas fa-video" style="color:#ff5252;font-size:17px"></i>
                            </div>
                            <div class="pstats">
                              <div class="ps"><span class="sv">24.1k</span><span class="sl">Subscribers</span></div>
                              <div class="ps"><span class="sv">486</span><span class="sl">Videos</span></div>
                              <div class="ps"><span class="sv">1.2M</span><span class="sl">Views</span></div>
                            </div>
                          </div>`;
                    case 'gallery': // Artist
                        return `<div class="hp-prof var-artist theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="swatch" aria-hidden="true">
                                  <i style="background:#e94e8c"></i>
                                  <i style="background:#ff8a3c"></i>
                                  <i style="background:#ffc845"></i>
                                  <i style="background:#1bd4d9"></i>
                                  <i style="background:#7c3aed"></i>
                                </div>
                              </div>
                              <i class="fas fa-palette" style="color:#ffc845;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'music':
                        return `<div class="hp-prof var-music theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <span class="npill"><i class="fas fa-music"></i>Now on tour · EP out</span>
                              </div>
                              <i class="fas fa-music" style="color:#1ed760;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'business':
                        return `<div class="hp-prof var-business theme-block" style="--d:0ms">
                            <div class="prow">
                              <div class="avwrap">${avatarImg}<span class="online" aria-hidden="true"></span></div>
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t} · Accepting briefs</div>
                                <div class="bbadges">
                                  <span class="bbadge">Strategy</span>
                                  <span class="bbadge">B2B</span>
                                  <span class="bbadge">SaaS</span>
                                </div>
                              </div>
                              <i class="fas fa-briefcase" style="color:#0ea5e9;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'coach':
                        return `<div class="hp-prof var-coach theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="chips">
                                  <span class="chip"><i class="fas fa-bolt"></i>NASM-CPT</span>
                                  <span class="chip"><i class="fas fa-star"></i>4.9 · 140+</span>
                                </div>
                              </div>
                              <i class="fas fa-dumbbell" style="color:#ffc845;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'portfolio': // Photographer
                        return `<div class="hp-prof var-photo theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="loc"><i class="fas fa-location-dot"></i>Reykjavík · Available Jun</div>
                                <div class="gear">
                                  <span class="gr">Sony A7R V</span>
                                  <span class="gr">24-70 GM</span>
                                  <span class="gr">RAW</span>
                                </div>
                              </div>
                              <i class="fas fa-camera-retro" style="color:#22d3ee;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'social': // Influencer
                        return `<div class="hp-prof var-social theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                              <i class="fas fa-fire" style="color:#ef4444;font-size:17px"></i>
                            </div>
                            <div class="fgrid">
                              <div class="fg"><div class="fv">312k</div><div class="fl"><i class="fas fa-users"></i> Followers</div></div>
                              <div class="fg"><div class="fv">180k</div><div class="fl"><i class="fas fa-eye"></i> Reach</div></div>
                              <div class="fg"><div class="fv">94k</div><div class="fl"><i class="fas fa-heart"></i> Likes</div></div>
                            </div>
                          </div>`;
                    case 'podcast':
                        return `<div class="hp-prof var-podcast theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <span class="air"><i aria-hidden="true"></i>On air · Ep 87 live</span>
                              </div>
                              <i class="fas fa-microphone-lines" style="color:#ff8a3c;font-size:17px"></i>
                            </div>
                          </div>`;
                    default:
                        return `<div class="hp-prof theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                            </div>
                          </div>`;
                }
            }

            // Creator — full biolink list; blocks are bigger and there
            // are more of them so the stack fills the phone screen.
            function renderCreator(role) {
                const blocks = (role.blocks || []).map((b, i) => {
                    const delay = (i + 1) * 110;
                    const thumb = b.thumb
                        ? pictureThumb(b.thumb, 'card-thumb', 50, 50, '50px', '')
                        : `<div class="card-icon" style="background:${escapeHTML(b.color)}33;color:${escapeHTML(b.color)}"><i class="${escapeHTML(b.icon)}"></i></div>`;
                    return `
                        <div class="stack-card theme-block" style="--d:${delay}ms">
                            ${thumb}
                            <div class="card-body">
                                <div class="card-title">${escapeHTML(b.title)}</div>
                                <div class="card-sub">${escapeHTML(b.sub || '')}</div>
                            </div>
                            <i class="fas fa-arrow-right card-cta"></i>
                        </div>`;
                }).join('');
                const last = (role.blocks || []).length * 110 + 120;
                return profFor(role) + blocks
                    + `<div class="hp-cta theme-block" style="--d:${last}ms"><i class="fas fa-hand-holding-heart"></i>Tip · Join members</div>`;
            }

            function renderMusic(role) {
                const cover  = pickFromGallery(role, 'Music', 0);
                const merch  = pickFromGallery(role, 'Merch', 1);
                const b0     = (role.blocks || [])[0] || {};
                return profFor(role)
                    + `<div class="hp-music-card theme-block" style="--d:110ms">
                            ${pictureThumb(cover, 'hp-music-cover', 280, 110, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="hp-music-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <div class="hp-music-meta">
                                <div class="mt"><div class="mt-t">${escapeHTML(b0.title || 'New EP')}</div><div class="mt-s">${escapeHTML(b0.sub || 'Listen now')}</div></div>
                                <div class="hp-music-play"><i class="fas fa-play" style="font-size:11px"></i></div>
                            </div>
                       </div>`
                    + `<div class="theme-block" style="--d:200ms">
                            <div class="hp-track"><span class="num">1</span><span class="nm">Saltwater</span><span class="du">3:42</span></div>
                       </div>`
                    + `<div class="theme-block" style="--d:250ms">
                            <div class="hp-track"><span class="num">2</span><span class="nm">Drift</span><span class="du">4:15</span></div>
                       </div>`
                    + `<div class="theme-block" style="--d:300ms">
                            <div class="hp-track"><span class="num">3</span><span class="nm">Afterglow</span><span class="du">3:28</span></div>
                       </div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:360ms">
                            <div class="ic" style="background:#e94e8c22;color:#fff"><i class="fas fa-ticket-alt"></i></div>
                            <div class="bd"><div class="bt">Tour 2026 · Tickets live</div><div class="bs">12 cities · Starts Jun 4</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-merch theme-block" style="--d:420ms">
                            ${pictureThumb(merch, '', 80, 80, '80px', '')}
                            <div class="mi"><div class="mt">Vinyl + tee bundle</div><div class="ms">Limited · Ships worldwide</div></div>
                            <span class="mp">$ 38</span>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:480ms"><i class="fas fa-headphones"></i>Stream · Save · Share</div>`;
            }

            function renderGallery(role) {
                const g = role.gallery || [];
                const cells = g.slice(0, 6).map((x) => `
                    <div class="gi">${pictureThumb(x.src, '', 100, 100, '100px', x.alt || '')}
                        <span class="badge">${escapeHTML(x.category)}</span>
                    </div>`).join('');
                const more = g.slice(6, 9).map((x) => `
                    <div class="gi">${pictureThumb(x.src, '', 100, 100, '100px', x.alt || '')}</div>`).join('');
                return profFor(role)
                    + `<div class="hp-grid-3 theme-block" style="--d:110ms">${cells}</div>`
                    + (more ? `<div class="hp-grid-3 theme-block" style="--d:200ms">${more}</div>` : '')
                    + `<div class="hp-stat-row theme-block" style="--d:260ms">
                            <div class="hp-stat"><div class="sv">86</div><div class="sl">Works</div></div>
                            <div class="hp-stat"><div class="sv">12</div><div class="sl">Shows</div></div>
                            <div class="hp-stat"><div class="sv">4.9</div><div class="sl">Rating</div></div>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:320ms"><i class="fas fa-shopping-bag"></i>Shop the collection</div>`
                    + `<div class="hp-cta dark theme-block" style="--d:380ms"><i class="fas fa-hand-holding-heart"></i>Tip jar</div>`;
            }

            function renderPortfolio(role) {
                const g = role.gallery || [];
                const feature = pickFromGallery(role, 'Photo', 0);
                const rest = g.filter(x => x.src !== feature);
                const grid4 = rest.slice(0, 4).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}</div>`).join('');
                const grid2 = rest.slice(4, 6).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}</div>`).join('');
                return profFor(role)
                    + `<div class="hp-feature theme-block" style="--d:110ms">
                            ${pictureThumb(feature, '', 280, 180, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="lbl"><span>Iceland · 2026</span><span><i class="fas fa-camera"></i> 48</span></div>
                       </div>`
                    + `<div class="hp-grid-2 theme-block" style="--d:200ms">${grid4}</div>`
                    + (grid2 ? `<div class="hp-grid-2 theme-block" style="--d:260ms">${grid2}</div>` : '')
                    + `<div class="hp-biz-cta theme-block" style="--d:320ms">
                            <div class="ic"><i class="fas fa-print"></i></div>
                            <div class="bd"><div class="bt">Fine-art print shop</div><div class="bs">A2 / A3 / canvas · Worldwide</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:380ms"><i class="fas fa-paper-plane"></i>Hire me · Weddings · Brand</div>`;
            }

            function renderBusiness(role) {
                return profFor(role)
                    + `<div class="hp-biz-cta theme-block" style="--d:110ms">
                            <div class="ic"><i class="fas fa-calendar-check"></i></div>
                            <div class="bd"><div class="bt">Book a strategy call</div><div class="bs">30 min · Calendly · Free intro</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:200ms">
                            <div class="hp-svc"><div class="st">Audit</div><div class="sp">$ 1.2k</div></div>
                            <div class="hp-svc"><div class="st">Retainer</div><div class="sp">$ 4.5k/mo</div></div>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:250ms">
                            <div class="hp-svc"><div class="st">Sprint</div><div class="sp">$ 2.5k</div></div>
                            <div class="hp-svc"><div class="st">Advisory</div><div class="sp">$ 600/hr</div></div>
                       </div>`
                    + `<div class="hp-stat-row theme-block" style="--d:320ms">
                            <div class="hp-stat"><div class="sv">120+</div><div class="sl">Clients</div></div>
                            <div class="hp-stat"><div class="sv">4.9</div><div class="sl">Rating</div></div>
                            <div class="hp-stat"><div class="sv">8 yr</div><div class="sl">Exp</div></div>
                       </div>`
                    + `<div class="hp-quote theme-block" style="--d:380ms">
                            Cut our CAC 38% in one quarter — Marcus is our unfair advantage.
                            <div class="qa"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span>· Priya, CEO</span></div>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:440ms"><i class="fas fa-paper-plane"></i>Get a proposal</div>`;
            }

            function renderCoach(role) {
                const reel = pickFromGallery(role, 'Video', 0);
                return profFor(role)
                    + `<div class="hp-stat-row theme-block" style="--d:110ms">
                            <div class="hp-stat"><div class="sv">140+</div><div class="sl">Clients</div></div>
                            <div class="hp-stat"><div class="sv">4.9★</div><div class="sl">Rating</div></div>
                            <div class="hp-stat"><div class="sv">12wk</div><div class="sl">Programs</div></div>
                       </div>`
                    + `<div class="hp-reel theme-block" style="--d:180ms">
                            ${pictureThumb(reel, '', 280, 360, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="ov"></div>
                            <div class="play"><i class="fas fa-play" style="font-size:12px"></i></div>
                            <div class="lb"><span><i class="fas fa-fire"></i> Form check</span><span><i class="fas fa-heart"></i> 12k</span></div>
                       </div>`
                    + `<div class="hp-quote theme-block" style="--d:260ms">
                            Lost 9 kg, deadlift up 40 kg in 12 weeks — Kai's plan just works.
                            <div class="qa"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span>· Sara, client</span></div>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:320ms">
                            <div class="hp-svc"><div class="st">1:1 Coach</div><div class="sp">$ 180/mo</div></div>
                            <div class="hp-svc"><div class="st">Group</div><div class="sp">$ 65/mo</div></div>
                       </div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:380ms">
                            <div class="ic"><i class="fas fa-calendar-check"></i></div>
                            <div class="bd"><div class="bt">Book a free consult</div><div class="bs">45 min · Zoom · Slots open</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-cta dark theme-block" style="--d:440ms"><i class="fas fa-clipboard-list"></i>Free intake form</div>`;
            }

            function renderPodcast(role) {
                const cover = pickFromGallery(role, 'Podcast', 0);
                return profFor(role)
                    + `<div class="hp-pod-card theme-block" style="--d:110ms">
                            ${pictureThumb(cover, '', 280, 160, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="pm">
                                <div class="pe">Ep. 87 · New</div>
                                <div class="pt">Building in public</div>
                                <div class="pd">42 min · Tech &amp; culture</div>
                            </div>
                            <div class="pp"><i class="fas fa-play" style="font-size:11px"></i></div>
                       </div>`
                    + `<div class="hp-wave theme-block" style="--d:200ms">
                            <span style="font-weight:800">2:14</span>
                            <svg viewBox="0 0 100 14" preserveAspectRatio="none"><polyline fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" points="0,8 8,4 14,11 22,3 30,9 38,5 46,12 54,2 62,9 70,6 78,11 86,4 94,9 100,7"/></svg>
                            <span style="opacity:.75">42:00</span>
                       </div>`
                    + `<div class="hp-ep theme-block" style="--d:260ms">
                            <span class="epn">#86</span><span class="ept">Shipping vs polishing</span><span class="epd">38m</span>
                       </div>`
                    + `<div class="hp-ep theme-block" style="--d:310ms">
                            <span class="epn">#85</span><span class="ept">Pricing your side project</span><span class="epd">45m</span>
                       </div>`
                    + `<div class="hp-stat-row theme-block" style="--d:360ms">
                            <div class="hp-stat"><div class="sv">87</div><div class="sl">Episodes</div></div>
                            <div class="hp-stat"><div class="sv">18k</div><div class="sl">Listeners</div></div>
                            <div class="hp-stat"><div class="sv">4.8</div><div class="sl">Rating</div></div>
                       </div>`
                    + `<div class="hp-cta dark theme-block" style="--d:420ms"><i class="fas fa-envelope-open-text"></i>Show notes &amp; newsletter</div>`;
            }

            function renderSocial(role) {
                const g = role.gallery || [];
                const reel = pickFromGallery(role, 'Video', 0);
                const stories = ['Reels','Hauls','Travel','Q&amp;A','BTS'];
                const storyHTML = stories.map((nm, i) => {
                    const src = (g[i] || g[0] || {}).src || '';
                    return `<div class="hp-story"><div class="ring">${pictureThumb(src, '', 56, 56, '56px', '')}</div><div class="nm">${nm}</div></div>`;
                }).join('');
                const posts = g.slice(0, 4).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}
                        <span class="hrt"><i class="fas fa-heart"></i>${Math.floor(Math.random()*80)+20}k</span>
                    </div>`).join('');
                return profFor(role)
                    + `<div class="hp-stories theme-block" style="--d:110ms">${storyHTML}</div>`
                    + `<div class="hp-reel theme-block" style="--d:180ms">
                            ${pictureThumb(reel, '', 280, 360, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="ov"></div>
                            <div class="play"><i class="fas fa-play" style="font-size:12px"></i></div>
                            <div class="lb"><span><i class="fas fa-eye"></i> 312k</span><span><i class="fas fa-heart"></i> 28k</span></div>
                       </div>`
                    + `<div class="hp-grid-4 theme-block" style="--d:250ms">${posts}</div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:320ms">
                            <div class="ic" style="background:#ffc84522;color:#fff"><i class="fas fa-handshake"></i></div>
                            <div class="bd"><div class="bt">Brand deals · Press kit</div><div class="bs">Rates · Past campaigns · Reach</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`;
            }

            const THEMES = {
                creator: renderCreator,
                gallery: renderGallery,
                portfolio: renderPortfolio,
                business: renderBusiness,
                coach: renderCoach,
                music: renderMusic,
                podcast: renderPodcast,
                social: renderSocial,
            };

            function buildStackHTML(role) {
                const fn = THEMES[role.theme] || renderCreator;
                return fn(role);
            }

            // ---- Compact horizontal interactive tile strip (all breakpoints) ----
            function buildTileRailHTML() {
                if (!tileRail) return;
                const html = ROLES.map((role, i) => {
                    const cat = (role.categories || [])[0];
                    const src = pickFromGallery(role, cat, 0);
                    const eager = i < 6;
                    const cover = src
                        ? pictureThumb(src, 'hero-tile-img', 80, 60, '80px', role.word + ' preview', { eager })
                        : fallbackTileCover(role);
                    return `<button type="button" class="hero-tile${i===0?' is-active':''}" `
                         + `data-role-i="${i}" aria-pressed="${i===0?'true':'false'}" `
                         + `aria-label="Show ${escapeHTML(role.word)} preview" tabindex="0">`
                         + `<span class="hero-tile-thumb">${cover}</span>`
                         + `<span class="hero-tile-label">${escapeHTML(role.word)}</span>`
                         + `</button>`;
                }).join('');
                tileRail.innerHTML = html;
            }

            function syncActiveTile(role) {
                if (!tileRail) return;
                const idx = ROLES.indexOf(role);
                const tiles = tileRail.querySelectorAll('.hero-tile');
                tiles.forEach((el, i) => {
                    const active = i === idx;
                    el.classList.toggle('is-active', active);
                    el.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                if (idx >= 0 && tiles[idx]) {
                    // Centre the active tile *within the rail's own horizontal
                    // scroll* — never use scrollIntoView, because on mobile the
                    // tile rail sits below the fold and scrollIntoView would
                    // also scroll the page vertically, pushing the hero
                    // headline / CTAs off-screen. We compute scrollLeft
                    // manually so only the rail moves.
                    try {
                        const tile = tiles[idx];
                        const target = tile.offsetLeft - (tileRail.clientWidth / 2) + (tile.offsetWidth / 2);
                        const max = Math.max(0, tileRail.scrollWidth - tileRail.clientWidth);
                        const left = Math.max(0, Math.min(max, target));
                        if (typeof tileRail.scrollTo === 'function') {
                            tileRail.scrollTo({ left, behavior: reduce ? 'auto' : 'smooth' });
                        } else {
                            tileRail.scrollLeft = left;
                        }
                    } catch (_) { /* no-op */ }
                }
            }

            function paintRoleVisuals(role) {
                applyWallpaper(role);
                if (gallery) gallery.innerHTML = buildGalleryHTML(role);
                if (galLbl) galLbl.textContent = role.word.toLowerCase();
                if (railLbl) railLbl.textContent = role.word.toLowerCase();
                syncActiveTile(role);
            }

            function setRole(role, opts) {
                opts = opts || {};
                if (opts.fromUser) pauseUntil = Date.now() + USER_PAUSE_MS;
                if (sr) sr.textContent = role.word;
                if (reduce) {
                    // Simple opacity crossfade fallback (no shimmer / animation)
                    word.classList.add('rm-out');
                    stack.classList.add('rm-out');
                    setTimeout(() => {
                        word.textContent = role.word;
                        stack.innerHTML = buildStackHTML(role);
                        paintRoleVisuals(role);
                        word.classList.remove('rm-out');
                        stack.classList.remove('rm-out');
                    }, 0);
                    return;
                }
                // Animate word out, swap text, animate in
                word.classList.remove('word-in');
                word.classList.add('word-out');
                // Animate stack out
                stack.classList.add('stack-out');
                setTimeout(() => {
                    word.textContent = role.word;
                    word.classList.remove('word-out');
                    // force reflow then play in
                    void word.offsetWidth;
                    word.classList.add('word-in');
                    stack.classList.remove('stack-out');
                    stack.innerHTML = buildStackHTML(role);
                    paintRoleVisuals(role);
                }, SWAP_MS);
            }

            let i = 0;
            // Build interactive rail before initial paint so syncActiveTile finds tiles.
            buildTileRailHTML();
            // Initial paint (no out animation)
            stack.innerHTML = buildStackHTML(ROLES[0]);
            word.textContent = ROLES[0].word;
            paintRoleVisuals(ROLES[0]);
            if (!reduce) word.classList.add('word-in');

            // Tile interactions: click pins (pauses auto-rotate), hover previews
            // without pinning (auto-rotate keeps running underneath).
            if (tileRail) {
                let hoverTimer = 0;
                const previewByIndex = (idx) => {
                    if (idx < 0 || idx >= ROLES.length) return;
                    i = idx; // keep auto-rotate counter in sync after preview
                    setRole(ROLES[idx]); // no fromUser → no pause
                };
                const pinByIndex = (idx) => {
                    if (idx < 0 || idx >= ROLES.length) return;
                    i = idx;
                    setRole(ROLES[idx], { fromUser: true }); // pauses rotate
                };
                tileRail.addEventListener('click', (e) => {
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    clearTimeout(hoverTimer);
                    pinByIndex(parseInt(tile.dataset.roleI, 10));
                });
                tileRail.addEventListener('mouseover', (e) => {
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    clearTimeout(hoverTimer);
                    const idx = parseInt(tile.dataset.roleI, 10);
                    hoverTimer = setTimeout(() => previewByIndex(idx), 140);
                });
                tileRail.addEventListener('mouseleave', () => {
                    clearTimeout(hoverTimer);
                });
                // Keyboard: arrow keys to move focus along the rail; Enter/Space activate via native button.
                tileRail.addEventListener('keydown', (e) => {
                    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' &&
                        e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    e.preventDefault();
                    const idx = parseInt(tile.dataset.roleI, 10);
                    const fwd = (e.key === 'ArrowRight' || e.key === 'ArrowDown');
                    const next = fwd
                        ? Math.min(ROLES.length - 1, idx + 1)
                        : Math.max(0, idx - 1);
                    const tiles = tileRail.querySelectorAll('.hero-tile');
                    if (tiles[next]) tiles[next].focus();
                });
            }

            setInterval(() => {
                if (Date.now() < pauseUntil) return;
                i = (i + 1) % ROLES.length;
                setRole(ROLES[i]);
            }, AUTO_ROTATE_MS);

            // Cursor parallax tilt on the phone (desktop only, no reduced motion).
            // Only attach the listener while the hero is on screen so we
            // don't burn CPU once the user has scrolled past it.
            if (phoneWrap && phoneScene && isDesktop && !reduce) {
                let raf = 0, tx = 0, ty = 0;
                const onMove = (e) => {
                    const r = phoneScene.getBoundingClientRect();
                    const cx = (e.clientX - r.left) / r.width  - 0.5;
                    const cy = (e.clientY - r.top)  / r.height - 0.5;
                    tx = -cy * 8; // rotateX
                    ty =  cx * 10; // rotateY
                    if (!raf) raf = requestAnimationFrame(() => {
                        phoneWrap.style.transform = `perspective(900px) rotateX(${tx}deg) rotateY(${ty}deg)`;
                        raf = 0;
                    });
                };
                const onLeave = () => { phoneWrap.style.transform = ''; };
                if ('IntersectionObserver' in window) {
                    let attached = false;
                    const io = new IntersectionObserver((entries) => {
                        const visible = entries[0]?.isIntersecting;
                        if (visible && !attached) {
                            phoneScene.addEventListener('mousemove', onMove, { passive: true });
                            phoneScene.addEventListener('mouseleave', onLeave);
                            attached = true;
                        } else if (!visible && attached) {
                            phoneScene.removeEventListener('mousemove', onMove);
                            phoneScene.removeEventListener('mouseleave', onLeave);
                            onLeave();
                            attached = false;
                        }
                    }, { threshold: 0.05 });
                    io.observe(phoneScene);
                } else {
                    phoneScene.addEventListener('mousemove', onMove, { passive: true });
                    phoneScene.addEventListener('mouseleave', onLeave);
                }
            }
        })();
    </script>
</section>

