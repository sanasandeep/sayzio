<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Models\BiolinkBlock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@1inme.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );

        // Ensure existing demo user is promoted to super_admin (this app stores
        // role on users.role, not via Spatie). Safe no-op if already correct.
        if (($user->role ?? null) !== 'super_admin') {
            $user->forceFill(['role' => 'super_admin'])->save();
        }

        $this->wipePreviousDemoLinks($user);

        $this->seedShortLinks($user);
        $this->seedBioLinks($user);
        $this->seedFileShares($user);
        $this->seedEventInvites($user);
        $this->seedDigitalCards($user);

        $this->command?->info('Seeded 25 demo links (5 per type) for demo@1inme.com');
    }

    private function wipePreviousDemoLinks(User $user): void
    {
        $links = Link::where('user_id', $user->id)
            ->where('alias', 'like', 'demo-%')
            ->get();

        foreach ($links as $link) {
            BiolinkBlock::where('link_id', $link->id)->delete();
            FileLink::where('link_id', $link->id)->delete();
            IcsData::where('link_id', $link->id)->delete();
            VcfData::where('link_id', $link->id)->delete();
            $link->delete();
        }
    }

    private function seedShortLinks(User $user): void
    {
        $samples = [
            ['demo-yt-lofi',     'Lo-fi Beats Playlist',         'https://www.youtube.com/results?search_query=lofi+beats'],
            ['demo-gh-laravel',  'Laravel on GitHub',            'https://github.com/laravel/laravel'],
            ['demo-wiki-qr',     'QR Codes — Wikipedia',         'https://en.wikipedia.org/wiki/QR_code'],
            ['demo-maps-eiffel', 'Eiffel Tower on Google Maps',  'https://www.google.com/maps?q=Eiffel+Tower'],
            ['demo-news-hn',     'Hacker News Front Page',       'https://news.ycombinator.com/'],
        ];

        foreach ($samples as [$alias, $title, $url]) {
            Link::create([
                'user_id' => $user->id,
                'type' => 'url',
                'alias' => $alias,
                'title' => $title,
                'long_url' => $url,
                'is_active' => true,
            ]);
        }
    }

    private function seedBioLinks(User $user): void
    {
        $pages = [
            [
                'alias' => 'demo-bio-creator',
                'title' => 'Mia · Content Creator',
                'heading' => "Hey, I'm Mia ✨",
                'paragraph' => 'Lifestyle creator sharing daily inspiration, fashion finds, and travel tips.',
                'links' => [
                    ['Latest YouTube video', 'https://www.youtube.com', 'fa-youtube'],
                    ['Shop my outfits',      'https://www.amazon.com',  'fa-bag-shopping'],
                    ['Newsletter sign-up',   'https://substack.com',    'fa-envelope'],
                    ['Book a 1:1 call',      'https://cal.com',          'fa-calendar'],
                ],
            ],
            [
                'alias' => 'demo-bio-musician',
                'title' => 'NovaWave · Indie Band',
                'heading' => 'NovaWave 🎸',
                'paragraph' => 'Indie rock from Berlin. New album out now — listen on your favourite platform.',
                'links' => [
                    ['Listen on Spotify', 'https://open.spotify.com',     'fa-spotify'],
                    ['Apple Music',       'https://music.apple.com',      'fa-apple'],
                    ['Tour dates',        'https://www.bandsintown.com',  'fa-calendar-days'],
                    ['Merch store',       'https://teespring.com',        'fa-shirt'],
                ],
            ],
            [
                'alias' => 'demo-bio-cafe',
                'title' => 'Cafe Bloom · Coffee & Bakery',
                'heading' => 'Cafe Bloom ☕',
                'paragraph' => 'Specialty coffee and freshly baked pastries in downtown. Open daily 7am–6pm.',
                'links' => [
                    ['View our menu',     'https://www.opentable.com',    'fa-utensils'],
                    ['Order delivery',    'https://www.ubereats.com',     'fa-truck'],
                    ['Reserve a table',   'https://www.opentable.com',    'fa-bookmark'],
                    ['Find us on Maps',   'https://maps.google.com',      'fa-map-location-dot'],
                ],
            ],
            [
                'alias' => 'demo-bio-coach',
                'title' => 'Alex · Life Coach',
                'heading' => 'Alex Rivera, ICF Coach',
                'paragraph' => 'Helping ambitious professionals find clarity, momentum, and balance.',
                'links' => [
                    ['Free clarity session',  'https://cal.com',                  'fa-calendar-check'],
                    ['Read my newsletter',    'https://substack.com',             'fa-envelope-open'],
                    ['Listen to the podcast', 'https://podcasts.apple.com',       'fa-podcast'],
                    ['Connect on LinkedIn',   'https://www.linkedin.com',         'fa-linkedin'],
                ],
            ],
            [
                'alias' => 'demo-bio-nonprofit',
                'title' => 'GreenFuture · Nonprofit',
                'heading' => 'GreenFuture 🌱',
                'paragraph' => 'Reforestation projects across South America. Every donation plants 10 trees.',
                'links' => [
                    ['Donate today',        'https://www.gofundme.com',  'fa-heart'],
                    ['Read our 2025 report','https://www.un.org',        'fa-file-pdf'],
                    ['Volunteer with us',   'https://www.volunteer.gov', 'fa-people-group'],
                    ['Share our mission',   'https://twitter.com',       'fa-share-nodes'],
                ],
            ],
        ];

        foreach ($pages as $page) {
            $link = Link::create([
                'user_id' => $user->id,
                'type' => 'biolink',
                'alias' => $page['alias'],
                'title' => $page['title'],
                'is_active' => true,
                'settings' => [
                    'biolink' => [
                        'biolink_title' => $page['title'],
                        'biolink_description' => $page['paragraph'],
                    ],
                ],
            ]);

            $sort = 0;

            BiolinkBlock::create([
                'link_id' => $link->id,
                'type' => 'heading',
                'sort_order' => $sort++,
                'is_active' => true,
                'settings' => ['text' => $page['heading'], 'level' => 1],
            ]);

            BiolinkBlock::create([
                'link_id' => $link->id,
                'type' => 'paragraph',
                'sort_order' => $sort++,
                'is_active' => true,
                'settings' => ['text' => $page['paragraph']],
            ]);

            foreach ($page['links'] as [$text, $url, $icon]) {
                BiolinkBlock::create([
                    'link_id' => $link->id,
                    'type' => 'link',
                    'sort_order' => $sort++,
                    'is_active' => true,
                    'settings' => [
                        'text' => $text,
                        'url' => $url,
                        'icon' => $icon,
                    ],
                ]);
            }
        }
    }

    private function seedFileShares(User $user): void
    {
        $files = [
            ['demo-file-resume',   'Sample-Resume.txt',      'sample-resume.txt',     "JANE DOE — Senior Designer\n\n10+ years of experience in product design.\nemail: jane@example.com"],
            ['demo-file-pricing',  'Pricing-Sheet.txt',      'pricing-sheet.txt',     "1INME PRICING\n\nFree   — \$0/mo\nPro    — \$9/mo\nBusiness — \$29/mo"],
            ['demo-file-menu',     'Cafe-Bloom-Menu.txt',    'cafe-menu.txt',         "CAFE BLOOM MENU\n\nEspresso  \$3.00\nLatte     \$4.00\nCroissant \$3.50\nMatcha    \$4.50"],
            ['demo-file-onepager', 'Product-One-Pager.txt',  'product-onepager.txt',  "1INME — Link management for everyone.\n\nShort links · Bio pages · QR · Forms · Files."],
            ['demo-file-press',    'Press-Kit.txt',          'press-kit.txt',         "1INME PRESS KIT\n\nLogos, screenshots, and brand guidelines.\nDownload at https://1inme.com/press"],
        ];

        foreach ($files as [$alias, $original, $stored, $content]) {
            $path = 'file-links/' . $stored;
            Storage::disk('public')->put($path, $content);

            $link = Link::create([
                'user_id' => $user->id,
                'type' => 'file',
                'alias' => $alias,
                'title' => $original,
                'is_active' => true,
            ]);

            FileLink::create([
                'link_id' => $link->id,
                'original_name' => $original,
                'stored_path' => $path,
                'mime_type' => 'text/plain',
                'file_size' => strlen($content),
                'disk' => 'public',
                'show_download_page' => true,
            ]);
        }
    }

    private function seedEventInvites(User $user): void
    {
        $events = [
            ['demo-evt-launch',  'Product Launch — 1INME v2',     'Online (Zoom)',           'https://zoom.us',                          10, 60],
            ['demo-evt-meetup',  'Berlin Tech Meetup',            'Berlin Hauptbahnhof',     'https://www.meetup.com',                   15, 180],
            ['demo-evt-webinar', 'SEO for Solopreneurs Webinar',  'Online',                  'https://www.youtube.com',                  20, 90],
            ['demo-evt-fest',    'SoundWave Music Festival',      'Tempelhof Field, Berlin', 'https://www.bandsintown.com',              30, 720],
            ['demo-evt-confab',  'Designers ConfAB 2026',         'San Francisco',           'https://www.eventbrite.com',               45, 480],
        ];

        foreach ($events as [$alias, $name, $location, $url, $daysAhead, $durationMin]) {
            $start = Carbon::now()->addDays($daysAhead)->setTime(18, 0);
            $end = (clone $start)->addMinutes($durationMin);

            $link = Link::create([
                'user_id' => $user->id,
                'type' => 'ics',
                'alias' => $alias,
                'title' => $name,
                'is_active' => true,
            ]);

            IcsData::create([
                'link_id' => $link->id,
                'event_name' => $name,
                'description' => 'Demo event added by 1INME starter content. Replace with your real event details.',
                'location' => $location,
                'organizer' => 'Demo Account',
                'organizer_email' => 'demo@1inme.com',
                'start_date' => $start,
                'end_date' => $end,
                'timezone' => 'UTC',
                'url' => $url,
            ]);
        }
    }

    private function seedDigitalCards(User $user): void
    {
        $cards = [
            ['demo-vcf-mia',  'Mia',  'Garcia', 'NovaContent',      'Content Creator',  'mia@example.com',  '+1 415 555 0101'],
            ['demo-vcf-alex', 'Alex', 'Rivera', 'Rivera Coaching',  'Life Coach',       'alex@example.com', '+1 415 555 0102'],
            ['demo-vcf-jon',  'Jon',  'Park',   'Cafe Bloom',       'Owner',            'jon@example.com',  '+1 415 555 0103'],
            ['demo-vcf-sara', 'Sara', 'Lin',    'GreenFuture',      'Director',         'sara@example.com', '+1 415 555 0104'],
            ['demo-vcf-luca', 'Luca', 'Romano', 'NovaWave',         'Lead Guitar',      'luca@example.com', '+1 415 555 0105'],
        ];

        foreach ($cards as [$alias, $first, $last, $org, $title, $email, $phone]) {
            $link = Link::create([
                'user_id' => $user->id,
                'type' => 'vcf',
                'alias' => $alias,
                'title' => "$first $last — $org",
                'is_active' => true,
            ]);

            VcfData::create([
                'link_id' => $link->id,
                'first_name' => $first,
                'last_name' => $last,
                'organization' => $org,
                'title' => $title,
                'email' => $email,
                'phone' => $phone,
                'website' => 'https://example.com',
                'city' => 'San Francisco',
                'country' => 'USA',
            ]);
        }
    }
}
