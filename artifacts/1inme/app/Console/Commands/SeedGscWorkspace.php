<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use Illuminate\Console\Command;

/**
 * One-off idempotent seeder: builds the "Global Startups Club" workspace for
 * sana@sayzio.app from https://www.globalstartups.club — events (summits,
 * retreats, monthly city meetups), a followable calendar, and biolink pages
 * (Summits, Retreats, one per meetup city). Safe to re-run: keyed by alias.
 */
class SeedGscWorkspace extends Command
{
    protected $signature = 'gsc:seed-workspace {--email=sana@sayzio.app}';
    protected $description = 'Seed the Global Startups Club workspace (events, calendar, biolink pages)';

    private const BRAND_YELLOW = '#FAD956';
    private const LOGO = 'https://static.wixstatic.com/media/cd1114_9355e828132c4bad8e387a684d3f7f20~mv2.png/v1/crop/x_317,y_968,w_3611,h_1686/fill/w_940,h_440,al_c,q_90/Global%20Startups%20Club%20Logo.png';
    private const SITE = 'https://www.globalstartups.club';

    public function handle(): int
    {
        $user = User::where('email', $this->option('email'))->first();
        if (!$user) {
            $this->error('User not found: ' . $this->option('email'));
            return 1;
        }

        $ws = Workspace::withoutGlobalScopes()->firstOrCreate(
            ['owner_user_id' => $user->id, 'slug' => 'global-startups-club'],
            [
                'name' => 'Global Startups Club',
                'is_personal' => false,
                'settings' => ['appearance' => ['icon' => 'fa-rocket', 'color' => self::BRAND_YELLOW]],
            ]
        );
        $this->info("Workspace #{$ws->id} Global Startups Club");

        $events = array_merge($this->summits(), $this->retreats(), $this->meetups());

        $eventLinks = [];
        foreach ($events as $e) {
            $eventLinks[$e['alias']] = $this->makeEvent($user, $ws, $e);
        }

        $this->makeCalendar($user, $ws, $events);

        $this->makeBiolink($user, $ws, 'gsc-summits', 'GSC Summits', $this->pageBlocks(
            'Global Startup Summits',
            "Our flagship conferences — 450+ delegates, 45+ speakers, 25+ investors. A platform for investors, aspiring entrepreneurs and business owners to start, manage & grow their business.",
            array_filter($events, fn ($e) => $e['group'] === 'summit'),
            $eventLinks
        ));

        $this->makeBiolink($user, $ws, 'gsc-retreats', 'GSC Founders\' Retreats', $this->pageBlocks(
            "Founders' Retreats",
            'A unique mix of a business startup growth program and a relaxing retreat for founders, business owners, investors and ecosystem collaborators.',
            array_filter($events, fn ($e) => $e['group'] === 'retreat'),
            $eventLinks
        ));

        // Per-city pages for the monthly club meetups.
        $byCity = [];
        foreach ($events as $e) {
            if ($e['group'] === 'meetup') {
                $byCity[$e['city']][] = $e;
            }
        }
        foreach ($byCity as $city => $list) {
            $slug = 'gsc-' . str()->slug($city);
            $this->makeBiolink($user, $ws, $slug, "GSC {$city}", $this->pageBlocks(
                "Global Startups Club — {$city}",
                "Monthly networking meetups in {$city}: founders, experts, investors and startup professionals over tea & coffee. Innovate. Network. Execute.",
                $list,
                $eventLinks
            ));
        }

        $this->info('Done. Events: ' . count($events) . ', city pages: ' . count($byCity));
        return 0;
    }

    private function makeEvent(User $user, Workspace $ws, array $e): Link
    {
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('alias', $e['alias'])->first();
        if ($link && $link->type !== 'ics') {
            throw new \RuntimeException("Alias {$e['alias']} exists with unexpected type {$link->type}; aborting.");
        }
        if (!$link) {
            $link = new Link([
                'user_id' => $user->id,
                'type' => 'ics',
                'alias' => $e['alias'],
                'title' => $e['name'],
                'is_active' => true,
                'visibility' => 'public',
                'settings' => ['event_category' => 'business'],
            ]);
        }
        $link->workspace_id = $ws->id;
        $link->save();

        IcsData::updateOrCreate(['link_id' => $link->id], [
            'event_name' => $e['name'],
            'description' => $e['desc'],
            'location' => $e['location'],
            'organizer' => 'Global Startups Club',
            'start_date' => $e['start'],
            'end_date' => $e['end'],
            'timezone' => $e['tz'],
            'url' => $e['url'] ?? self::SITE,
            'all_day' => $e['all_day'] ?? false,
            'cover_image_url' => $e['image'] ?? null,
            'hashtags' => ['startups', 'networking', 'globalstartupsclub'],
        ]);

        $this->line("event  {$e['alias']}");
        return $link;
    }

    private function makeCalendar(User $user, Workspace $ws, array $events): void
    {
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('alias', 'gsc-calendar')->first();
        if ($link && $link->type !== 'calendar') {
            throw new \RuntimeException('Alias gsc-calendar exists with unexpected type ' . $link->type . '; aborting.');
        }
        if (!$link) {
            $link = new Link([
                'user_id' => $user->id,
                'type' => 'calendar',
                'alias' => 'gsc-calendar',
                'title' => 'Global Startups Club — Events Calendar',
                'is_active' => true,
                'visibility' => 'public',
            ]);
        }
        $link->workspace_id = $ws->id;
        $link->save();

        $cal = Calendar::firstOrCreate(['link_id' => $link->id], [
            'user_id' => $user->id,
            'title' => 'Global Startups Club — Events Calendar',
            'slug' => 'gsc-calendar',
            'description' => 'All upcoming Global Startups Club events: summits, founders\' retreats and monthly city meetups.',
            'timezone' => 'Asia/Kolkata',
            'accent_color' => self::BRAND_YELLOW,
            'is_public' => true,
        ]);
        if ($link->calendar_id !== $cal->id) {
            $link->calendar_id = $cal->id;
            $link->save();
        }

        foreach ($events as $e) {
            CalendarEvent::updateOrCreate(
                ['calendar_id' => $cal->id, 'title' => $e['name'], 'start_at' => $e['start']],
                [
                    'user_id' => $user->id,
                    'description' => $e['desc'],
                    'end_at' => $e['end'],
                    'timezone' => $e['tz'],
                    'all_day' => $e['all_day'] ?? false,
                    'location' => $e['location'],
                    'payment_url' => $e['url'] ?? self::SITE,
                    'hashtags' => ['startups', 'networking'],
                ]
            );
        }
        $this->line('calendar gsc-calendar (' . count($events) . ' events)');
    }

    private function makeBiolink(User $user, Workspace $ws, string $alias, string $title, array $blocks): void
    {
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('alias', $alias)->first();
        if ($link && $link->type !== 'biolink') {
            throw new \RuntimeException("Alias {$alias} exists with unexpected type {$link->type}; aborting.");
        }
        if (!$link) {
            $link = new Link([
                'user_id' => $user->id,
                'type' => 'biolink',
                'alias' => $alias,
                'title' => $title,
                'is_active' => true,
                'visibility' => 'public',
                'settings' => [
                    'seo' => ['title' => $title],
                    'background_type' => 'color',
                    'background_color' => '#0b0b0b',
                    'text_color' => '#ffffff',
                    'button_color' => self::BRAND_YELLOW,
                    'button_text_color' => '#111111',
                ],
            ]);
        }
        $link->workspace_id = $ws->id;
        $link->save();

        // Convergent: rebuild the managed block set atomically so re-runs
        // repair pages left partial by an interrupted earlier run.
        \Illuminate\Support\Facades\DB::transaction(function () use ($link, $blocks) {
            BiolinkBlock::where('link_id', $link->id)->delete();
            $now = now();
            BiolinkBlock::insert(array_map(fn ($b, $i) => [
                'link_id' => $link->id,
                'type' => $b['type'],
                'settings' => json_encode($b['settings']),
                'sort_order' => $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $blocks, array_keys($blocks)));
        });
        $this->line("page   {$alias} (" . count($blocks) . ' blocks)');
    }

    private function pageBlocks(string $heading, string $intro, array $events, array $eventLinks): array
    {
        $blocks = [
            ['type' => 'image', 'settings' => ['url' => self::LOGO, 'alt' => 'Global Startups Club', 'link' => self::SITE]],
            ['type' => 'heading', 'settings' => ['text' => $heading, 'size' => 'h2', 'align' => 'center', 'style' => 'plain']],
            ['type' => 'paragraph', 'settings' => ['text' => $intro, 'align' => 'center']],
        ];
        usort($events, fn ($a, $b) => strcmp($a['start'], $b['start']));
        foreach ($events as $e) {
            $date = \Illuminate\Support\Carbon::parse($e['start'])->format('j M Y');
            $blocks[] = ['type' => 'link', 'settings' => [
                'text' => "{$date} — {$e['name']}",
                'url' => url('/' . $e['alias']),
                'icon' => 'fa-calendar-days',
            ]];
        }
        $blocks[] = ['type' => 'link', 'settings' => [
            'text' => 'Full Events Calendar',
            'url' => url('/gsc-calendar'),
            'icon' => 'fa-calendar',
        ]];
        $blocks[] = ['type' => 'link', 'settings' => [
            'text' => 'globalstartups.club',
            'url' => self::SITE,
            'icon' => 'fa-globe',
        ]];
        return $blocks;
    }

    // ---- Data scraped from globalstartups.club (Aug 2026 refresh) ----

    private function summits(): array
    {
        $reg = self::SITE . '/event-details-registration/';
        $desc = 'A platform for investors, aspiring entrepreneurs and business owners to start, manage & grow their business. 450+ delegates, 45+ speakers, 25+ investors.';
        return [
            ['group' => 'summit', 'alias' => 'gsc-summit-kolkata-2026', 'name' => 'Global Startup Summit | Kolkata 2026', 'start' => '2026-07-25 08:30:00', 'end' => '2026-07-25 18:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'Kolkata, India', 'desc' => $desc, 'url' => $reg . 'global-startup-summit-2026-kolkata', 'city' => 'Kolkata'],
            ['group' => 'summit', 'alias' => 'gsc-summit-pune-2026', 'name' => 'Global Startup Summit | Pune 2026', 'start' => '2026-08-22 08:30:00', 'end' => '2026-08-22 18:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'E-Square The Fern Pune, Series by Marriott, Level 5, 133A, University Rd, Ganeshkhind, Pune 411016', 'desc' => $desc, 'url' => $reg . 'global-startup-summit-pune-2026', 'image' => 'https://static.wixstatic.com/media/c7c940_86ee5e10b6a342898b86a45ef5ffdaff~mv2.jpeg/v1/fill/w_2944,h_1288,al_c,q_90/c7c940_86ee5e10b6a342898b86a45ef5ffdaff~mv2.jpeg', 'city' => 'Pune'],
            ['group' => 'summit', 'alias' => 'gsc-summit-hyderabad-2026', 'name' => 'Global Startup Summit | Hyderabad 2026', 'start' => '2026-09-19 08:30:00', 'end' => '2026-09-19 18:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'Hyderabad, India', 'desc' => $desc, 'url' => $reg . 'global-startup-summit-hyderabad-2026', 'city' => 'Hyderabad'],
            ['group' => 'summit', 'alias' => 'gsc-summit-chennai-2026', 'name' => 'Global Startup Summit | Chennai 2026', 'start' => '2026-10-31 08:30:00', 'end' => '2026-10-31 18:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'Chennai, India', 'desc' => $desc, 'url' => $reg . 'global-startup-summit-2026-chennai', 'city' => 'Chennai'],
            ['group' => 'summit', 'alias' => 'gsc-dubai-delegation-2027', 'name' => 'International Startup Delegation — Dubai 2027', 'start' => '2027-12-07 08:00:00', 'end' => '2027-12-14 22:00:00', 'tz' => 'Asia/Dubai', 'location' => 'Dubai, United Arab Emirates', 'desc' => 'A platform for investors, founders and aspiring entrepreneurs to manage & grow their business — an international startup delegation to Dubai.', 'url' => $reg . 'international-startup-delegation-dubai-2026', 'image' => 'https://static.wixstatic.com/media/11062b_858d8caee7ce4ece9db5a3cdd3cb83af~mv2.jpg/v1/fill/w_5000,h_2650,al_c,q_90/11062b_858d8caee7ce4ece9db5a3cdd3cb83af~mv2.jpg', 'city' => 'Dubai'],
        ];
    }

    private function retreats(): array
    {
        $reg = self::SITE . '/event-details-registration/';
        $desc = "A unique mix of a business startup growth program and a relaxing retreat for founders, business owners, startups, investors, service professionals & other ecosystem collaborators.";
        return [
            ['group' => 'retreat', 'alias' => 'gsc-retreat-goa-2026', 'name' => "Founders' Retreat 2026 — Goa", 'start' => '2026-10-01 10:00:00', 'end' => '2026-10-04 16:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'Azara Resort and Spa, Anjuna, Goa 403509, India', 'desc' => $desc, 'url' => $reg . 'founders-retreat-2026-goa', 'city' => 'Goa'],
            ['group' => 'retreat', 'alias' => 'gsc-retreat-lonavala-2027', 'name' => "Founders' Retreat Lonavala 2027 — AI", 'start' => '2027-02-26 10:00:00', 'end' => '2027-02-28 17:00:00', 'tz' => 'Asia/Kolkata', 'location' => 'Mayur Retreat & Spa, Old Mumbai–Pune Hwy, Lonavala, Maharashtra 410401, India', 'desc' => $desc, 'url' => $reg . 'founders-retreat-lonavala-2027-ai', 'city' => 'Lonavala'],
            ['group' => 'retreat', 'alias' => 'gsc-retreat-bali-2027', 'name' => "Founders' Retreat 2027 — Bali", 'start' => '2027-05-13 10:00:00', 'end' => '2027-05-16 16:00:00', 'tz' => 'Asia/Makassar', 'location' => 'Bali, Indonesia', 'desc' => "4 days, 3 nights — " . $desc, 'url' => $reg . 'founders-retreat-2027-bali', 'image' => 'https://static.wixstatic.com/media/11062b_2fe92d5ea94b43e5933d24ceb2e26188~mv2.jpg/v1/fill/w_4256,h_2832,al_c,q_90/11062b_2fe92d5ea94b43e5933d24ceb2e26188~mv2.jpg', 'city' => 'Bali'],
        ];
    }

    private function meetups(): array
    {
        $rows = [
            ['Noida', '2026-08-01', 'Startup Networking', 'https://allevents.in/noida/global-startups-club-l-startup-networking-noida-2026-tickets/80001686279536', 'Asia/Kolkata'],
            ['Hyderabad', '2026-08-01', 'Startup Networking', 'https://go.allevents.in/iln6s', 'Asia/Kolkata'],
            ['Bengaluru', '2026-08-01', "Founder's Breakfast Club", 'https://go.allevents.in/2zfs0', 'Asia/Kolkata'],
            ['Jakarta', '2026-08-02', 'Startup Networking', 'https://allevents.in/jakarta/global-startups-club-l-startup-networking-jakarta-2026-tickets/80001128282350', 'Asia/Jakarta'],
            ['Dubai', '2026-08-06', 'Startup Networking', 'https://allevents.in/dubai/global-startups-club-startup-networking-dubai-2026-tickets/80001141365691', 'Asia/Dubai'],
            ['Johannesburg', '2026-08-06', 'Startup Networking', 'https://go.allevents.in/edjk9', 'Africa/Johannesburg'],
            ['Visakhapatnam', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/55knd', 'Asia/Kolkata'],
            ['Thane', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/fc3cr', 'Asia/Kolkata'],
            ['Coimbatore', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/fsrqt', 'Asia/Kolkata'],
            ['Mumbai', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/br141', 'Asia/Kolkata'],
            ['Ahmedabad', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/kixh2', 'Asia/Kolkata'],
            ['Bengaluru', '2026-08-08', 'Startup Networking', 'https://go.allevents.in/roacj', 'Asia/Kolkata'],
            ['Cape Town', '2026-08-13', 'Startup Networking', 'https://go.allevents.in/0n5ta', 'Africa/Johannesburg'],
            ['Toronto', '2026-08-15', 'Startup Networking', null, 'America/Toronto'],
            ['Vancouver', '2026-08-15', 'Startup Networking', null, 'America/Vancouver'],
            ['Sydney', '2026-08-15', 'Startup Networking', 'https://go.allevents.in/7jtfd', 'Australia/Sydney'],
            ['Boston', '2026-08-15', 'Startup Networking', null, 'America/New_York'],
            ['Mumbai', '2026-08-22', 'Founders Breakfast Club', 'https://go.allevents.in/4t66p', 'Asia/Kolkata'],
            ['Hyderabad', '2026-08-22', 'Startup Networking', 'https://go.allevents.in/l4v2s', 'Asia/Kolkata'],
            ['Jaipur', '2026-08-22', 'Startup Networking', null, 'Asia/Kolkata'],
            ['Dubai', '2026-08-27', 'Startup Networking', null, 'Asia/Dubai'],
            ['Singapore', '2026-08-28', 'Startup Networking', null, 'Asia/Singapore'],
            ['Chennai', '2026-08-29', 'Startup Networking', 'https://go.allevents.in/tu2l1', 'Asia/Kolkata'],
            ['Mumbai', '2026-08-29', 'Startup Networking', 'https://go.allevents.in/tu2l1', 'Asia/Kolkata'],
            ['Kolkata', '2026-08-29', 'Startup Networking', 'https://go.allevents.in/2glo0', 'Asia/Kolkata'],
            ['Dallas', '2026-08-29', 'Startup Networking', null, 'America/Chicago'],
        ];
        $out = [];
        foreach ($rows as [$city, $date, $kind, $url, $tz]) {
            $slugCity = str()->slug($city);
            $slugKind = str_contains($kind, 'Breakfast') ? 'fbc' : 'meetup';
            $out[] = [
                'group' => 'meetup',
                'alias' => "gsc-{$slugKind}-{$slugCity}-" . str_replace('-', '', substr($date, 5)),
                'name' => "Global Startups Club — {$kind} | {$city}",
                'start' => $date . ' 00:00:00',
                'end' => $date . ' 23:59:00',
                'tz' => $tz,
                'all_day' => true,
                'location' => $city,
                'desc' => "Global Startups Club monthly {$kind} in {$city}: a networking hub bringing together founders, experts, consultants, influential leaders and startup professionals. Innovate. Network. Execute.",
                'url' => $url,
                'city' => $city,
            ];
        }
        return $out;
    }
}
