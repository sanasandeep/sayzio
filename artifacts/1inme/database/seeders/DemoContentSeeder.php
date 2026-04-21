<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Subscriber;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@1inme.com'],
            [
                'name'     => 'Demo User',
                'password' => Hash::make('password'),
                'role'     => 'super_admin',
            ]
        );

        if (($user->role ?? null) !== 'super_admin') {
            $user->forceFill(['role' => 'super_admin'])->save();
        }
        if (! $user->is_demo) {
            $user->forceFill(['is_demo' => true])->save();
        }

        $this->wipePreviousDemoContent();

        $this->seedShortLinks($user);
        $this->seedBioLinks($user);
        $this->seedFileShares($user);
        $this->seedEventInvites($user);
        $this->seedDigitalCards($user);

        // Multi-creator content for the discover/feed experience.
        $creators = $this->seedDemoCreators();
        $this->seedCreatorBiolinks($creators);
        $this->seedFeedPosts($creators);
        $this->seedFollowsAndSubscriptions($user, $creators);

        $this->command?->info('Seeded demo content: 25 links for demo@1inme.com + ' . count($creators) . ' demo creators with biolinks, feed posts, and follow/subscribe relationships.');
    }

    /** Wipe everything previously created by this seeder, in any tier. */
    public static function wipeAllDemoContent(): array
    {
        $stats = ['users' => 0, 'links' => 0, 'feed_events' => 0, 'follows' => 0, 'subscribers' => 0];

        // Demo users (excluding the seed super-admin so the admin retains login).
        $demoUserIds = User::where('is_demo', true)
            ->where('email', '!=', 'demo@1inme.com')
            ->pluck('id')->all();

        $stats['feed_events'] = FeedEvent::where('is_demo', true)->orWhereIn('user_id', $demoUserIds)->delete();
        $stats['follows']     = Follow::whereIn('creator_id', $demoUserIds)->orWhereIn('follower_id', $demoUserIds)->delete();
        $stats['subscribers'] = Subscriber::whereIn('user_id', $demoUserIds)->delete();

        // Delete demo links + their child biolink_blocks/file/ics/vcf data.
        // Strictly flag-based: only rows explicitly marked is_demo=true OR
        // owned by a known demo user. We deliberately do NOT match on
        // `alias LIKE 'demo-%'` so a real user's link starting with "demo-"
        // is never deleted.
        $linkIds = Link::where(function ($q) use ($demoUserIds) {
                $q->where('is_demo', true);
                if ($demoUserIds) $q->orWhereIn('user_id', $demoUserIds);
            })
            ->pluck('id')->all();
        if ($linkIds) {
            BiolinkBlock::whereIn('link_id', $linkIds)->delete();
            FileLink::whereIn('link_id', $linkIds)->delete();
            IcsData::whereIn('link_id', $linkIds)->delete();
            VcfData::whereIn('link_id', $linkIds)->delete();
            $stats['links'] = Link::whereIn('id', $linkIds)->delete();
        }

        $stats['users'] = User::whereIn('id', $demoUserIds)->delete();

        return $stats;
    }

    /** Counts of currently-present demo content (used by the admin dashboard). */
    public static function demoContentStats(): array
    {
        return [
            'users'       => User::where('is_demo', true)->where('email', '!=', 'demo@1inme.com')->count(),
            'links'       => Link::where('is_demo', true)->count(),
            'feed_events' => FeedEvent::where('is_demo', true)->count(),
            'demo_user'   => User::where('email', 'demo@1inme.com')->exists(),
        ];
    }

    private function wipePreviousDemoContent(): void
    {
        self::wipeAllDemoContent();
    }

    // ── Single demo-user content (links of every type) ─────────────────

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
                'user_id'    => $user->id,
                'type'       => 'url',
                'alias'      => $alias,
                'title'      => $title,
                'long_url'   => $url,
                'is_active'  => true,
                'visibility' => 'public',
                'is_demo'    => true,
            ]);
        }
    }

    private function seedBioLinks(User $user): void
    {
        $pages = [
            [
                'alias' => 'demo-bio-musician',
                'title' => 'NovaWave Band',
                'heading' => 'NovaWave 🎶',
                'paragraph' => 'Indie rock from San Francisco. New EP out now.',
                'visibility' => 'public',
                'links' => [
                    ['Listen on Spotify',  'https://open.spotify.com',  'fa-spotify'],
                    ['Buy concert tickets','https://www.eventbrite.com','fa-ticket'],
                    ['Watch on YouTube',   'https://www.youtube.com',   'fa-youtube'],
                ],
            ],
            [
                'alias' => 'demo-bio-coach',
                'title' => 'Alex Rivera Coaching',
                'heading' => 'Alex Rivera, ICF Coach',
                'paragraph' => 'Helping ambitious professionals find clarity and momentum.',
                'visibility' => 'registered',
                'links' => [
                    ['Free clarity session', 'https://cal.com',           'fa-calendar-check'],
                    ['Read my newsletter',   'https://substack.com',      'fa-envelope-open'],
                    ['Connect on LinkedIn',  'https://www.linkedin.com',  'fa-linkedin'],
                ],
            ],
            [
                'alias' => 'demo-bio-nonprofit',
                'title' => 'GreenFuture · Nonprofit',
                'heading' => 'GreenFuture 🌱',
                'paragraph' => 'Reforestation projects across South America.',
                'visibility' => 'public',
                'links' => [
                    ['Donate today',         'https://www.gofundme.com', 'fa-heart'],
                    ['Volunteer with us',    'https://www.volunteer.gov','fa-people-group'],
                ],
            ],
        ];

        foreach ($pages as $page) {
            $this->createBiolink($user, $page);
        }
    }

    private function createBiolink(User $owner, array $page): Link
    {
        $link = Link::create([
            'user_id'    => $owner->id,
            'type'       => 'biolink',
            'alias'      => $page['alias'],
            'title'      => $page['title'],
            'is_active'  => true,
            'visibility' => $page['visibility'] ?? 'public',
            'is_demo'    => true,
            'settings'   => [
                'biolink' => [
                    'biolink_title'       => $page['title'],
                    'biolink_description' => $page['paragraph'],
                ],
            ],
        ]);

        $sort = 0;
        BiolinkBlock::create(['link_id' => $link->id, 'type' => 'heading',   'sort_order' => $sort++, 'is_active' => true, 'settings' => ['text' => $page['heading'], 'level' => 1]]);
        BiolinkBlock::create(['link_id' => $link->id, 'type' => 'paragraph', 'sort_order' => $sort++, 'is_active' => true, 'settings' => ['text' => $page['paragraph']]]);
        foreach (($page['links'] ?? []) as [$text, $url, $icon]) {
            BiolinkBlock::create([
                'link_id'    => $link->id,
                'type'       => 'link',
                'sort_order' => $sort++,
                'is_active'  => true,
                'settings'   => ['text' => $text, 'url' => $url, 'icon' => $icon],
            ]);
        }
        return $link;
    }

    private function seedFileShares(User $user): void
    {
        $files = [
            ['demo-file-resume', 'Sample-Resume.txt',  "JANE DOE — Senior Designer\n\n10+ years of experience.\nemail: jane@example.com"],
            ['demo-file-pricing','Pricing-Guide.txt',  "PRICING\n\nStarter \$10\nPro \$25\nTeam \$60"],
        ];
        foreach ($files as [$alias, $name, $body]) {
            $link = Link::create([
                'user_id' => $user->id, 'type' => 'file', 'alias' => $alias, 'title' => $name,
                'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
            ]);
            FileLink::create([
                'link_id'       => $link->id,
                'original_name' => $name,
                'mime_type'     => 'text/plain',
                'file_size'     => strlen($body),
                'stored_path'   => 'demo/' . Str::slug($alias) . '.txt',
                'disk'          => 'public',
            ]);
        }
    }

    private function seedEventInvites(User $user): void
    {
        $events = [
            ['demo-ics-launch', 'Product Launch Party', 'Online', '+3 days'],
            ['demo-ics-meetup', 'SF Designers Meetup',  'San Francisco', '+10 days'],
        ];
        foreach ($events as [$alias, $title, $loc, $when]) {
            $link = Link::create([
                'user_id' => $user->id, 'type' => 'ics', 'alias' => $alias, 'title' => $title,
                'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
            ]);
            IcsData::create([
                'link_id'     => $link->id,
                'event_name'  => $title,
                'description' => "Demo event: $title",
                'location'    => $loc,
                'start_date'  => Carbon::parse($when),
                'end_date'    => Carbon::parse($when)->addHours(2),
                'timezone'    => 'UTC',
            ]);
        }
    }

    private function seedDigitalCards(User $user): void
    {
        $cards = [
            ['demo-vcf-mia',  'Mia',  'Garcia', 'NovaContent', 'Content Creator', 'mia@example.com',  '+1 415 555 0101'],
            ['demo-vcf-alex', 'Alex', 'Rivera', 'Rivera Coaching', 'Life Coach',   'alex@example.com', '+1 415 555 0102'],
        ];
        foreach ($cards as [$alias, $first, $last, $org, $title, $email, $phone]) {
            $link = Link::create([
                'user_id' => $user->id, 'type' => 'vcf', 'alias' => $alias,
                'title'   => "$first $last — $org",
                'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
            ]);
            VcfData::create([
                'link_id' => $link->id, 'first_name' => $first, 'last_name' => $last,
                'organization' => $org, 'title' => $title, 'email' => $email, 'phone' => $phone,
                'website' => 'https://example.com', 'city' => 'San Francisco', 'country' => 'USA',
            ]);
        }
    }

    // ── Multi-creator content for the feed/discover experience ──────────

    /** @return array<int,User> */
    private function seedDemoCreators(): array
    {
        $personas = [
            ['mia',    'Mia Garcia',    'NovaContent · Content Creator',  '🎬 Vlogs about creative living, weekly drops.'],
            ['kai',    'Kai Tanaka',    'Photographer · Tokyo',           '📷 Street + portrait photography from Tokyo.'],
            ['lena',   'Lena Schmidt',  'Founder · Cofoundery',           '🚀 Building tools for indie founders. Behind the scenes.'],
            ['ravi',   'Ravi Mehta',    'Chef · Spice Route',             '🍜 Modern Indian recipes, supper-club drops.'],
            ['sara',   'Sara Lin',      'Director · GreenFuture',         '🌱 Climate action, field updates from reforestation projects.'],
            ['jordan', 'Jordan Reeves', 'Producer · LoFi Lab',            '🎧 Lo-fi beats, weekly mixtape & sample packs.'],
        ];

        $creators = [];
        foreach ($personas as [$handle, $name, $persona, $bio]) {
            $email = "demo-{$handle}@1inme.com";
            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'         => $name,
                    'password'     => Hash::make('password'),
                    'persona'      => $persona,
                    'bio'          => $bio,
                    'handle'       => $handle,
                    'discoverable' => true,
                    'allow_followers' => true,
                    'is_demo'      => true,
                ]
            );
            // Ensure flags stick even if user existed.
            $u->forceFill([
                'persona' => $persona, 'bio' => $bio, 'handle' => $handle,
                'discoverable' => true, 'allow_followers' => true, 'is_demo' => true,
            ])->save();
            $creators[] = $u;
        }
        return $creators;
    }

    /** @param array<int,User> $creators */
    private function seedCreatorBiolinks(array $creators): void
    {
        $tiers = ['public', 'public', 'registered', 'followers', 'subscribers', 'public'];
        foreach ($creators as $i => $c) {
            $vis = $tiers[$i % count($tiers)];
            $page = [
                'alias'       => "demo-{$c->handle}",
                'title'       => $c->name,
                'heading'     => $c->name,
                'paragraph'   => $c->bio ?? 'Creator on 1INME.',
                'visibility'  => $vis,
                'links'       => [
                    ['Latest on Instagram',  'https://www.instagram.com', 'fa-instagram'],
                    ['Subscribe',            'https://example.com/sub',   'fa-bell'],
                    ['Shop merch',           'https://example.com/shop',  'fa-shopping-bag'],
                ],
            ];
            $this->createBiolink($c, $page);
        }
    }

    /** Create feed_events for each creator across all visibility tiers. */
    private function seedFeedPosts(array $creators): void
    {
        $tiers = ['public', 'registered', 'followers', 'subscribers'];
        $samples = [
            ['🎬 Behind the scenes from this week\'s shoot.',   'photo'],
            ['🔥 New project just dropped — link in bio.',      'launch'],
            ['💌 Quick update for my supporters — thank you!',  'update'],
            ['🎁 Subscriber-only sneak peek of what\'s next.',  'gift'],
            ['📅 New session opens tomorrow at 9am.',           'event'],
            ['📸 Just posted a new gallery, take a look.',      'post'],
        ];

        foreach ($creators as $i => $c) {
            foreach ($samples as $j => [$body, $kind]) {
                FeedEvent::create([
                    'user_id'      => $c->id,
                    'type'         => 'creator_post',
                    'subject_id'   => null,
                    'subject_type' => 'demo',
                    'data'         => ['body' => $body, 'kind' => $kind],
                    'visibility'   => $tiers[($i + $j) % count($tiers)],
                    'is_demo'      => true,
                    'occurred_at'  => Carbon::now()->subHours($j * 5 + $i),
                ]);
            }
        }
    }

    /** Make the demo super-admin follow every demo creator + add a few subscribers. */
    private function seedFollowsAndSubscriptions(User $admin, array $creators): void
    {
        foreach ($creators as $c) {
            Follow::firstOrCreate([
                'follower_id' => $admin->id,
                'creator_id'  => $c->id,
            ]);

            // Sprinkle some demo email subscribers so subscriber-only content
            // has at least one recipient to demonstrate access.
            Subscriber::firstOrCreate(
                ['user_id' => $c->id, 'type' => 'email', 'email' => $admin->email],
                ['status' => 'active']
            );
        }
    }
}
