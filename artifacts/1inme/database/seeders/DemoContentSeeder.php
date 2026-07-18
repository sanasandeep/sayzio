<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\ConversationAction;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskLabel;
use App\Modules\User\Models\TaskSubtask;
use App\Modules\User\Models\TaskComment;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskAttachment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'sayzioapp@gmail.com'],
            [
                'name'     => 'Demo User',
                'password' => Hash::make('password'),
            ]
        );

        // Make the demo account a user-admin so the demo experience
        // shows the platform-administration sidebar items end-to-end.
        $userAdminRoleId = DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            $user->roles()->syncWithoutDetaching([$userAdminRoleId]);
            $user->flushPermissionCache();
        }

        if (! $user->is_demo) {
            $user->forceFill(['is_demo' => true])->save();
        }

        // Task #3768 — populate a rich organizer profile so demo event
        // pages render the full "Hosted by" card (logo/bio/website/contact/
        // socials) instead of the bare avatar+name fallback. Idempotent:
        // only writes when the profile isn't already filled in, so a
        // real admin edit to the demo account's organizer profile sticks.
        if (! ($user->organizerProfile()['filled'] ?? false)) {
            $user->organizer_profile = [
                'logo'          => 'https://picsum.photos/seed/demo-organizer/200/200',
                'name'          => 'Demo Events Co.',
                'description'   => 'We host community meetups, workshops, and pop-up experiences around the city — come say hi!',
                'website'       => 'https://1in.me',
                'contact_name'  => 'Demo User',
                'contact_email' => 'sayzioapp@gmail.com',
                'contact_phone' => '+1 (555) 010-0100',
                'address'       => 'San Francisco, CA',
                'socials'       => [
                    'instagram' => '@demoeventsco',
                    'twitter'   => '@demoeventsco',
                ],
            ];
            $user->save();
        }

        $this->wipePreviousDemoContent();

        $personalWs = $user->ensureDefaultWorkspace();

        // Single demo-user content (links of every type) — all attached to
        // the demo admin's personal workspace so they show up in the
        // dashboard out of the box.
        $this->seedShortLinks($user, $personalWs);
        $this->seedBioLinks($user, $personalWs);
        $this->seedFileShares($user, $personalWs);
        $this->seedEventInvites($user, $personalWs);
        $this->seedDigitalCards($user, $personalWs);

        // Multi-workspace collaboration: 1 personal + 4 team workspaces,
        // each with a handful of demo members and a populated task board.
        $teamMembers   = $this->seedTeamMemberAccounts();
        $teamWorkspaces = $this->seedTeamWorkspaces($user, $teamMembers);
        $this->seedTaskBoards(array_merge([$personalWs], $teamWorkspaces), $user, $teamMembers);

        // Multi-creator content for the discover/feed experience.
        $creators = $this->seedDemoCreators();
        $this->seedCreatorBiolinks($creators);
        $this->seedFeedPosts($creators);
        $this->seedFollowsAndSubscriptions($user, $creators);

        $stats = self::demoContentStats();
        $this->command?->info(sprintf(
            'Seeded demo content: %d links, %d demo workspaces, %d task cards across %d boards, %d demo team members, %d demo creators, %d feed posts.',
            $stats['links'], $stats['workspaces'], $stats['task_cards'], $stats['task_boards'],
            $stats['team_members'], $stats['creators'], $stats['feed_events']
        ));
    }

    // ── Wipe / stats ─────────────────────────────────────────────────────

    /** Wipe everything previously created by this seeder. */
    public static function wipeAllDemoContent(): array
    {
        $stats = [
            'users' => 0, 'links' => 0, 'feed_events' => 0,
            'follows' => 0, 'subscribers' => 0,
            'workspaces' => 0, 'task_boards' => 0, 'task_cards' => 0,
        ];

        // Demo users (excluding the seed super-admin so the admin retains login).
        $demoAdmin = User::where('email', 'sayzioapp@gmail.com')->first();
        $demoAdminId = $demoAdmin?->id;
        $otherDemoUserIds = User::where('is_demo', true)
            ->where('email', '!=', 'sayzioapp@gmail.com')
            ->pluck('id')->all();
        $allDemoUserIds = $demoAdminId
            ? array_values(array_unique(array_merge([$demoAdminId], $otherDemoUserIds)))
            : $otherDemoUserIds;

        // Demo workspaces: every workspace owned by a demo user, EXCEPT the
        // demo admin's own personal workspace (kept so they retain login state).
        $demoWorkspaceQ = Workspace::query()->whereIn('owner_user_id', $allDemoUserIds);
        if ($demoAdminId) {
            $demoWorkspaceQ->where(function ($q) use ($demoAdminId) {
                $q->where('owner_user_id', '!=', $demoAdminId)
                  ->orWhere('is_personal', false);
            });
        }
        $demoWorkspaceIds = $demoWorkspaceQ->pluck('id')->all();

        // The demo admin's personal workspace is NOT deleted (so the admin
        // retains their login + default workspace). Only seeded boards on it
        // are wiped, by name, so the auto-provisioned "My Tasks" board is
        // preserved.
        $adminPersonalWsId = $demoAdmin
            ? optional(Workspace::where('owner_user_id', $demoAdminId)->where('is_personal', true)->first())->id
            : null;
        $seededBoardNames = ['Roadmap', 'Content Calendar', 'Demo · My Tasks'];

        // Collect every task board to wipe:
        //   - All boards in demo workspaces (these workspaces are deleted
        //     below, and task tables have no DB-level cascade) — this also
        //     catches auto-provisioned `PersonalTaskBoardProvisioner` boards.
        //   - On the admin's preserved personal workspace, only boards we
        //     seeded ourselves (matched by name).
        $boardIds = [];
        if ($demoWorkspaceIds) {
            $boardIds = TaskBoard::query()->withoutWorkspaceScope()
                ->whereIn('workspace_id', $demoWorkspaceIds)
                ->pluck('id')->all();
        }
        if ($adminPersonalWsId) {
            $adminBoardIds = TaskBoard::query()->withoutWorkspaceScope()
                ->where('workspace_id', $adminPersonalWsId)
                ->whereIn('name', $seededBoardNames)
                ->pluck('id')->all();
            $boardIds = array_values(array_unique(array_merge($boardIds, $adminBoardIds)));
        }

        if ($boardIds || $demoWorkspaceIds) {
            $cardIds  = $boardIds
                ? TaskCard::query()->withoutWorkspaceScope()->whereIn('board_id', $boardIds)->pluck('id')->all()
                : [];

            if ($cardIds) {
                DB::table('task_card_assignees')->whereIn('card_id', $cardIds)->delete();
                DB::table('task_card_labels')->whereIn('card_id', $cardIds)->delete();
                TaskSubtask::query()->withoutWorkspaceScope()->whereIn('card_id', $cardIds)->delete();
                TaskComment::query()->withoutWorkspaceScope()->whereIn('card_id', $cardIds)->delete();
                TaskActivity::query()->withoutWorkspaceScope()->whereIn('card_id', $cardIds)->delete();
                TaskAttachment::query()->withoutWorkspaceScope()->whereIn('card_id', $cardIds)->delete();
                $stats['task_cards'] = TaskCard::query()->withoutWorkspaceScope()->whereIn('id', $cardIds)->delete();
            }
            if ($boardIds) {
                TaskLabel::query()->withoutWorkspaceScope()->whereIn('board_id', $boardIds)->delete();
                TaskColumn::query()->withoutWorkspaceScope()->whereIn('board_id', $boardIds)->delete();
                $stats['task_boards'] = TaskBoard::query()->withoutWorkspaceScope()->whereIn('id', $boardIds)->delete();
            }

            WorkspaceMember::whereIn('workspace_id', $demoWorkspaceIds)->delete();
            WorkspaceInvite::whereIn('workspace_id', $demoWorkspaceIds)->delete();
            $stats['workspaces'] = Workspace::whereIn('id', $demoWorkspaceIds)->delete();
        }

        // Feed events / follows / subscribers tied to demo users.
        $stats['feed_events'] = FeedEvent::where('is_demo', true)
            ->orWhereIn('user_id', $allDemoUserIds)->delete();
        $stats['follows'] = Follow::query()->withoutWorkspaceScope()
            ->where(function ($q) use ($allDemoUserIds) {
                $q->whereIn('creator_id', $allDemoUserIds)
                  ->orWhereIn('follower_id', $allDemoUserIds);
            })->delete();
        $stats['subscribers'] = Subscriber::query()->withoutWorkspaceScope()
            ->whereIn('user_id', $allDemoUserIds)->delete();

        // Delete demo links + their child biolink_blocks/file/ics/vcf data.
        // Strictly flag-based: only rows explicitly marked is_demo=true OR
        // owned by a known demo user.
        $linkIds = Link::query()->withoutWorkspaceScope()
            ->where(function ($q) use ($allDemoUserIds) {
                $q->where('is_demo', true);
                if ($allDemoUserIds) $q->orWhereIn('user_id', $allDemoUserIds);
            })
            ->pluck('id')->all();
        if ($linkIds) {
            BiolinkBlock::whereIn('link_id', $linkIds)->delete();
            FileLink::whereIn('link_id', $linkIds)->delete();
            IcsData::whereIn('link_id', $linkIds)->delete();
            VcfData::whereIn('link_id', $linkIds)->delete();
            \App\Modules\User\Models\EventTicketTier::whereIn('link_id', $linkIds)->delete();
            $stats['links'] = Link::query()->withoutWorkspaceScope()->whereIn('id', $linkIds)->delete();
        }

        // Finally drop the non-admin demo users themselves.
        $stats['users'] = $otherDemoUserIds
            ? User::whereIn('id', $otherDemoUserIds)->delete()
            : 0;

        return $stats;
    }

    /** Counts of currently-present demo content (used by the admin dashboard). */
    public static function demoContentStats(): array
    {
        $demoAdmin = User::where('email', 'sayzioapp@gmail.com')->first();
        $demoAdminId = $demoAdmin?->id;
        $otherDemoUserIds = User::where('is_demo', true)
            ->where('email', '!=', 'sayzioapp@gmail.com')
            ->pluck('id')->all();
        $allDemoUserIds = $demoAdminId
            ? array_values(array_unique(array_merge([$demoAdminId], $otherDemoUserIds)))
            : $otherDemoUserIds;

        $demoWorkspaceQ = Workspace::query()->whereIn('owner_user_id', $allDemoUserIds);
        if ($demoAdminId) {
            $demoWorkspaceQ->where(function ($q) use ($demoAdminId) {
                $q->where('owner_user_id', '!=', $demoAdminId)
                  ->orWhere('is_personal', false);
            });
        }
        $demoWorkspaceIds = $demoWorkspaceQ->pluck('id')->all();

        $teamMemberCount = User::where('is_demo', true)
            ->where('email', 'like', 'demo-team-%@1inme.com')->count();
        $creatorCount = User::where('is_demo', true)
            ->where('email', 'like', 'demo-%@1inme.com')
            ->where('email', 'not like', 'demo-team-%@1inme.com')
            ->whereNotNull('handle')
            ->count();

        return [
            // `users` is kept as the total non-admin demo accounts so the
            // existing admin view (which labels this card "Demo creators")
            // still displays a sensible number, but we also expose
            // `creators` and `team_members` separately for the new view.
            'users'        => count($otherDemoUserIds),
            'creators'     => $creatorCount,
            'team_members' => $teamMemberCount,
            'links'        => Link::query()->withoutWorkspaceScope()->where('is_demo', true)->count(),
            'feed_events'  => FeedEvent::where('is_demo', true)->count(),
            // Workspaces count includes the demo admin's personal workspace
            // when present (we don't delete it on wipe, but it is part of the
            // demo footprint the user signs in to).
            'workspaces'   => count($demoWorkspaceIds) + ($demoAdminId ? 1 : 0),
            'task_boards'  => TaskBoard::query()->withoutWorkspaceScope()
                ->whereIn('name', ['Roadmap', 'Content Calendar', 'Demo · My Tasks'])
                ->when($demoAdminId, fn ($q) => $q->where(function ($q) use ($demoWorkspaceIds, $demoAdminId) {
                    $q->whereIn('workspace_id', $demoWorkspaceIds)
                      ->orWhere('owner_user_id', $demoAdminId);
                }))
                ->count(),
            'task_cards'   => TaskCard::query()->withoutWorkspaceScope()
                ->whereIn('board_id', TaskBoard::query()->withoutWorkspaceScope()
                    ->whereIn('name', ['Roadmap', 'Content Calendar', 'Demo · My Tasks'])
                    ->when($demoAdminId, fn ($q) => $q->where(function ($q) use ($demoWorkspaceIds, $demoAdminId) {
                        $q->whereIn('workspace_id', $demoWorkspaceIds)
                          ->orWhere('owner_user_id', $demoAdminId);
                    }))
                    ->pluck('id'))
                ->count(),
            'demo_user'    => (bool) $demoAdmin,
        ];
    }

    private function wipePreviousDemoContent(): void
    {
        self::wipeAllDemoContent();
    }

    // ── Single demo-user content (links of every type) ─────────────────

    private function seedShortLinks(User $user, Workspace $ws): void
    {
        // 25 short links across a wide variety of destinations / titles, with
        // a realistic spread of click counts so analytics views aren't empty.
        $samples = [
            ['demo-yt-lofi',     'Lo-fi Beats Playlist',         'https://www.youtube.com/results?search_query=lofi+beats'],
            ['demo-gh-laravel',  'Laravel on GitHub',            'https://github.com/laravel/laravel'],
            ['demo-wiki-qr',     'QR Codes — Wikipedia',         'https://en.wikipedia.org/wiki/QR_code'],
            ['demo-maps-eiffel', 'Eiffel Tower on Google Maps',  'https://www.google.com/maps?q=Eiffel+Tower'],
            ['demo-news-hn',     'Hacker News Front Page',       'https://news.ycombinator.com/'],
            ['demo-dribbble',    'Inspiration on Dribbble',      'https://dribbble.com/shots/popular'],
            ['demo-figma-tour',  'Figma Product Tour',           'https://www.figma.com/'],
            ['demo-spotify-mix', 'Friday Mixtape on Spotify',    'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M'],
            ['demo-podcast-ep', 'Latest Podcast Episode',        'https://example.com/podcast/episode-42'],
            ['demo-blog-launch','Blog: Why we launched',         'https://example.com/blog/why-we-launched'],
            ['demo-store-tee',  'Limited Edition Tee',           'https://example.com/store/tee'],
            ['demo-store-mug',  'Ceramic Mug · Sold Out Soon',   'https://example.com/store/mug'],
            ['demo-coupon-fall','Autumn Sale — 25% off',         'https://example.com/sale/fall'],
            ['demo-newsletter', 'Subscribe to the Newsletter',   'https://example.com/newsletter'],
            ['demo-investors',  'Investors — pitch deck',        'https://example.com/investors/deck'],
            ['demo-careers',    'We\'re hiring — Careers',       'https://example.com/careers'],
            ['demo-press-kit',  'Press Kit (logos + bios)',      'https://example.com/press-kit'],
            ['demo-yt-tour',    'Studio tour — YouTube',         'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ['demo-tw-thread',  'Thread: 10 launch lessons',     'https://twitter.com/example/status/1'],
            ['demo-li-post',    'LinkedIn announcement',         'https://www.linkedin.com/posts/example-activity-1'],
            ['demo-affiliate',  'Affiliate signup link',         'https://example.com/partner?ref=demo'],
            ['demo-survey-q3',  'Q3 customer survey',            'https://example.com/survey/q3'],
            ['demo-event-rsvp', 'Workshop RSVP',                 'https://example.com/events/workshop-rsvp'],
            ['demo-app-android','Android app on Play Store',     'https://play.google.com/store/apps'],
            ['demo-app-ios',    'iOS app on App Store',          'https://apps.apple.com/'],
        ];

        // A couple of "spice" examples — one password-protected, one
        // expiring next week, one expired last month — so the link-list
        // view shows badges next to entries.
        $now = now();
        foreach ($samples as $i => [$alias, $title, $url]) {
            $clicks = (int) round(20 + sin($i * 0.7) * 18 + $i * 6);  // 20..200ish
            $unique = (int) max(1, round($clicks * (0.6 + ($i % 5) * 0.05)));

            $settings = [];
            $expiresAt = null;
            $password  = null;
            $isProtected = false;
            $utm = ['source' => null, 'medium' => null, 'campaign' => null];

            if ($i % 6 === 0) {
                $utm = ['source' => 'newsletter', 'medium' => 'email',  'campaign' => 'fall_2026'];
            } elseif ($i % 6 === 1) {
                $utm = ['source' => 'twitter',    'medium' => 'social', 'campaign' => 'launch_announce'];
            } elseif ($i % 6 === 2) {
                $utm = ['source' => 'youtube',    'medium' => 'video',  'campaign' => 'studio_tour'];
            }

            if ($alias === 'demo-coupon-fall') {
                $expiresAt = $now->copy()->addDays(7);
            }
            if ($alias === 'demo-survey-q3') {
                $expiresAt = $now->copy()->subDays(30); // already expired
            }
            if ($alias === 'demo-investors') {
                $password = Hash::make('investors2026');
                $isProtected = true;
            }

            Link::forceCreate([
                'workspace_id' => $ws->id,
                'user_id'      => $user->id,
                'created_by_user_id' => $user->id,
                'type'         => 'url',
                'alias'        => $alias,
                'title'        => $title,
                'long_url'     => $url,
                'is_active'    => true,
                'visibility'   => 'public',
                'is_demo'      => true,
                'expires_at'   => $expiresAt,
                'password'     => $password,
                'is_password_protected' => $isProtected,
                'utm_source'   => $utm['source'],
                'utm_medium'   => $utm['medium'],
                'utm_campaign' => $utm['campaign'],
                'total_clicks' => $clicks,
                'unique_clicks'=> $unique,
                'settings'     => $settings,
            ]);
        }
    }

    private function seedBioLinks(User $user, Workspace $ws): void
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
            [
                'alias' => 'demo-bio-shop',
                'title' => 'Studio Otter — Shop',
                'heading' => 'Studio Otter 🛍️',
                'paragraph' => 'Hand-printed posters and homewares. New drops every month.',
                'visibility' => 'public',
                'links' => [
                    ['Shop the new drop',  'https://example.com/shop/new', 'fa-shopping-bag'],
                    ['Wholesale enquiries','https://example.com/wholesale','fa-envelope'],
                    ['Shipping & returns', 'https://example.com/shipping', 'fa-truck'],
                ],
            ],
            [
                'alias' => 'demo-bio-restaurant',
                'title' => 'Casa Nori — Reservations',
                'heading' => 'Casa Nori 🍣',
                'paragraph' => 'Modern Japanese in the Mission. Open Tue–Sun.',
                'visibility' => 'public',
                'links' => [
                    ['Book a table',     'https://example.com/book', 'fa-calendar-check'],
                    ['View the menu',    'https://example.com/menu', 'fa-utensils'],
                    ['Private events',   'https://example.com/private-events', 'fa-champagne-glasses'],
                ],
            ],
            [
                'alias' => 'demo-bio-author',
                'title' => 'M. Park · Novelist',
                'heading' => 'M. Park ✍️',
                'paragraph' => 'New novel out next spring. Tour dates inside.',
                'visibility' => 'followers',
                'links' => [
                    ['Pre-order the novel', 'https://example.com/preorder', 'fa-book'],
                    ['Tour dates',          'https://example.com/tour',     'fa-calendar'],
                    ['Mailing list',        'https://example.com/list',     'fa-envelope'],
                ],
            ],
            [
                'alias' => 'demo-bio-product',
                'title' => 'Kettle CRM · Links',
                'heading' => 'Kettle CRM',
                'paragraph' => 'CRM that doesn\'t get in your way. Try it free.',
                'visibility' => 'public',
                'links' => [
                    ['Start free trial', 'https://example.com/start',   'fa-play'],
                    ['Pricing',          'https://example.com/pricing', 'fa-tag'],
                    ['Customer stories', 'https://example.com/stories', 'fa-quote-left'],
                ],
            ],
            [
                'alias' => 'demo-bio-pro-only',
                'title' => 'Pro members only',
                'heading' => 'Members area 🔒',
                'paragraph' => 'Bonus resources for paying subscribers.',
                'visibility' => 'subscribers',
                'links' => [
                    ['Bonus episode #12', 'https://example.com/bonus/12', 'fa-headphones'],
                    ['Member discount',   'https://example.com/member-discount', 'fa-percent'],
                ],
            ],
        ];

        foreach ($pages as $page) {
            $this->createBiolink($user, $page, $ws);
        }
    }

    private function createBiolink(User $owner, array $page, ?Workspace $ws = null): Link
    {
        $linkAttrs = [
            'user_id'    => $owner->id,
            'type'       => 'biolink',
            'alias'      => $page['alias'],
            'title'      => $page['title'],
            'is_active'  => true,
            'visibility' => $page['visibility'] ?? 'public',
            'is_demo'    => true,
            'settings'   => [
                'biolink' => array_merge([
                    'biolink_title'       => $page['title'],
                    'biolink_description' => $page['paragraph'],
                ], $page['biolink_settings'] ?? []),
            ],
        ];
        if ($ws) {
            $linkAttrs['workspace_id'] = $ws->id;
            $linkAttrs['created_by_user_id'] = $owner->id;
        }

        $link = Link::forceCreate($linkAttrs);

        $sort = 0;
        BiolinkBlock::forceCreate(['link_id' => $link->id, 'type' => 'heading',   'sort_order' => $sort++, 'is_active' => true, 'settings' => ['text' => $page['heading'], 'level' => 1]]);
        BiolinkBlock::forceCreate(['link_id' => $link->id, 'type' => 'paragraph', 'sort_order' => $sort++, 'is_active' => true, 'settings' => ['text' => $page['paragraph']]]);
        foreach (($page['links'] ?? []) as [$text, $url, $icon]) {
            BiolinkBlock::forceCreate([
                'link_id'    => $link->id,
                'type'       => 'link',
                'sort_order' => $sort++,
                'is_active'  => true,
                'settings'   => ['text' => $text, 'url' => $url, 'icon' => $icon],
            ]);
        }
        return $link;
    }

    private function seedFileShares(User $user, Workspace $ws): void
    {
        $files = [
            ['demo-file-resume',  'Sample-Resume.txt',    "JANE DOE — Senior Designer\n\n10+ years of experience.\nemail: jane@example.com"],
            ['demo-file-pricing', 'Pricing-Guide.txt',    "PRICING\n\nStarter \$10\nPro \$25\nTeam \$60"],
            ['demo-file-deck',    'Pitch-Deck-Q3.txt',    "Q3 Pitch Deck\n\nSlide 1: Problem\nSlide 2: Solution\nSlide 3: Market"],
            ['demo-file-contract','Standard-Contract.txt',"STANDARD SERVICES AGREEMENT\n\nThis Agreement is entered into…"],
            ['demo-file-brief',   'Creative-Brief.txt',   "CREATIVE BRIEF\n\nClient: NovaWave\nDeliverables: poster, social pack"],
            ['demo-file-recipe',  'Ramen-Recipe.txt',     "TONKOTSU RAMEN\n\nIngredients:\n- pork bones\n- aromatics"],
        ];
        $now = now();
        foreach ($files as $i => [$alias, $name, $body]) {
            $link = Link::forceCreate([
                'workspace_id' => $ws->id,
                'user_id' => $user->id,
                'created_by_user_id' => $user->id,
                'type' => 'file', 'alias' => $alias, 'title' => $name,
                'is_active' => true, 'visibility' => $i % 4 === 3 ? 'subscribers' : 'public',
                'is_demo' => true,
                'total_clicks' => 12 + $i * 7,
                'unique_clicks' => 8 + $i * 5,
            ]);
            FileLink::forceCreate([
                'link_id'       => $link->id,
                'original_name' => $name,
                'mime_type'     => 'text/plain',
                'file_size'     => strlen($body),
                'stored_path'   => 'demo/' . Str::slug($alias) . '.txt',
                'disk'          => 'public',
            ]);
        }
    }

    /**
     * ~100 diverse demo events for the /events directory overhaul (Task
     * #3647). Spans all 16 curated categories, a mix of online/physical,
     * ticketed/RSVP/plain, past/upcoming, with hashtags + cover images so the
     * hero slider, category tiles, trending row and map search all have
     * something realistic to show. Idempotent: aliases are deterministic
     * (`demo-evt-###`) and wipePreviousDemoContent() clears prior runs first.
     */
    private function seedEventInvites(User $user, Workspace $ws): void
    {
        // A handful of hand-authored "flagship" events (kept from the
        // original seed) so existing aliases referenced elsewhere still work.
        $flagship = [
            ['demo-ics-launch',  'Product Launch Party',     'Online',           '+3 days',  'technology',  true,  false, ['launch', 'product', 'tech']],
            ['demo-ics-meetup',  'SF Designers Meetup',      'San Francisco',    '+10 days', 'community',   false, false, ['design', 'meetup', 'sf']],
            ['demo-ics-webinar', 'Q4 Strategy Webinar',      'Online (Zoom)',    '+14 days', 'business',    true,  false, ['webinar', 'strategy']],
            ['demo-ics-dinner',  'Founder Dinner — Series A','New York',         '+21 days', 'business',    false, true,  ['founders', 'dinner']],
            ['demo-ics-hackday', 'Hack Day for Climate',     'Brooklyn, NY',     '+30 days', 'technology',  false, false, ['climate', 'hackathon']],
            ['demo-ics-pastevent','Recap: Spring Showcase',  'Los Angeles',      '-15 days', 'arts',        false, false, ['recap', 'showcase']],
        ];

        // City pool (lat/lng) so physical events plot sensibly on the map
        // search and "near me" filter.
        $cities = [
            ['San Francisco, CA', 37.7749, -122.4194],
            ['New York, NY',       40.7128,  -74.0060],
            ['Austin, TX',         30.2672,  -97.7431],
            ['Chicago, IL',        41.8781,  -87.6298],
            ['Seattle, WA',        47.6062, -122.3321],
            ['Miami, FL',          25.7617,  -80.1918],
            ['Denver, CO',         39.7392, -104.9903],
            ['Boston, MA',         42.3601,  -71.0589],
            ['London, UK',         51.5072,   -0.1276],
            ['Berlin, Germany',    52.5200,   13.4050],
            ['Toronto, Canada',    43.6532,  -79.3832],
            ['Sydney, Australia', -33.8688,  151.2093],
            ['Tokyo, Japan',       35.6762,  139.6503],
            ['Bengaluru, India',   12.9716,   77.5946],
            ['Lisbon, Portugal',   38.7223,   -9.1393],
            ['Mexico City, Mexico',19.4326,  -99.1332],
        ];

        // Per-category title/hashtag templates so events read as believable
        // real-world happenings rather than "Event #42".
        $templates = [
            'music'           => [['{c} Live Sessions', ['livemusic', 'gig']], ['Indie Night at {c}', ['indie', 'music']], ['{c} Jazz Evening', ['jazz', 'music']]],
            'nightlife'       => [['{c} Rooftop Party', ['party', 'nightlife']], ['Sunset Social — {c}', ['social', 'drinks']], ['Neon Nights: {c}', ['nightlife', 'dj']]],
            'arts'            => [['{c} Gallery Opening', ['art', 'gallery']], ['Sculpture Walk — {c}', ['sculpture', 'arts']], ['{c} Photography Exhibit', ['photography', 'exhibit']]],
            'film'            => [['{c} Short Film Night', ['shortfilm', 'cinema']], ['Documentary Screening — {c}', ['documentary', 'film']], ['{c} Film Festival Kickoff', ['filmfest', 'movies']]],
            'comedy'          => [['Stand-Up Night — {c}', ['standup', 'comedy']], ['{c} Improv Showcase', ['improv', 'comedy']], ['Laugh Lab: {c}', ['comedy', 'funny']]],
            'food_drink'      => [['{c} Street Food Fair', ['streetfood', 'foodie']], ['Craft Beer Tasting — {c}', ['craftbeer', 'tasting']], ['{c} Wine & Cheese Night', ['wine', 'foodpairing']]],
            'technology'      => [['{c} Startup Demo Day', ['startup', 'demoday']], ['AI Builders Meetup — {c}', ['ai', 'buildinpublic']], ['{c} DevOps Summit', ['devops', 'engineering']]],
            'business'        => [['{c} Founders Breakfast', ['founders', 'networking']], ['Growth Marketing Panel — {c}', ['marketing', 'growth']], ['{c} Leadership Roundtable', ['leadership', 'business']]],
            'education'       => [['{c} Career Workshop', ['careers', 'workshop']], ['Public Speaking Bootcamp — {c}', ['publicspeaking', 'learning']], ['{c} Coding for Beginners', ['coding', 'education']]],
            'community'       => [['{c} Neighbors Meetup', ['community', 'meetup']], ['Volunteers Day — {c}', ['volunteer', 'giveback']], ['{c} Newcomers Social', ['newcomers', 'community']]],
            'sports_fitness'  => [['{c} 5K Fun Run', ['5k', 'running']], ['Yoga in the Park — {c}', ['yoga', 'wellness']], ['{c} Pickup Basketball League', ['basketball', 'sports']]],
            'health_wellness' => [['Mindfulness Retreat — {c}', ['mindfulness', 'wellness']], ['{c} Nutrition Workshop', ['nutrition', 'health']], ['Sound Bath Session — {c}', ['soundbath', 'relax']]],
            'outdoor_travel'  => [['{c} Hiking Club Meetup', ['hiking', 'outdoors']], ['Sunrise Kayak Tour — {c}', ['kayak', 'adventure']], ['{c} Weekend Camping Trip', ['camping', 'travel']]],
            'gaming'          => [['{c} Esports Tournament', ['esports', 'gaming']], ['Board Game Night — {c}', ['boardgames', 'gamenight']], ['{c} Retro Arcade Meetup', ['retrogaming', 'arcade']]],
            'fashion'         => [['{c} Streetwear Pop-Up', ['streetwear', 'fashion']], ['Runway Show — {c}', ['runway', 'fashionweek']], ['{c} Vintage Swap Meet', ['vintage', 'thrift']]],
            'charity'         => [['{c} Charity Gala', ['charity', 'gala']], ['Fundraiser 5K — {c}', ['fundraiser', 'charity']], ['{c} Food Drive Kickoff', ['fooddrive', 'giveback']]],
        ];

        $categorySlugs = array_keys($templates);
        $now = now();
        $created = 0;

        // Skip aliases already seeded (idempotent + resumable across
        // interrupted runs) — one bulk lookup instead of a per-alias check.
        $existingAliases = Link::query()->withoutWorkspaceScope()
            ->where(function ($q) {
                $q->where('alias', 'like', 'demo-evt-%')->orWhere('alias', 'like', 'demo-ics-%');
            })
            ->pluck('alias')->flip()->all();

        foreach ($flagship as $i => [$alias, $title, $loc, $when, $category, $online, $ticketed, $tags]) {
            if (isset($existingAliases[$alias])) {
                $created++;
                continue;
            }
            $city = $cities[$i % count($cities)];
            $this->makeDemoEvent($user, $ws, [
                'alias' => $alias, 'title' => $title, 'location' => $loc,
                'start' => Carbon::parse($when), 'category' => $category,
                'online' => $online, 'ticketed' => $ticketed, 'rsvp' => !$ticketed,
                'hashtags' => $tags,
                'lat' => $online ? null : $city[1], 'lng' => $online ? null : $city[2],
                'clicks' => 30 + $i * 9, 'idx' => $i,
            ]);
            $created++;
        }

        // Generate the remaining ~94 events by cycling category × city ×
        // template combinations with a deterministic pseudo-random spread of
        // dates (past + upcoming), online/physical, and ticketed/RSVP/plain.
        $target = 100;
        $n = 0;
        while ($created < $target) {
            $category = $categorySlugs[$n % count($categorySlugs)];
            $city = $cities[$n % count($cities)];
            $tplSet = $templates[$category];
            $tpl = $tplSet[$n % count($tplSet)];
            $cityShort = trim(explode(',', $city[0])[0]);
            $title = str_replace('{c}', $cityShort, $tpl[0]);
            $baseTags = $tpl[1];

            // Deterministic pseudo-random spread: ~15% past, rest spread
            // across the next 90 days; ~20% online; ~35% ticketed, ~40%
            // plain RSVP, ~25% no RSVP (informational only).
            $dayOffset = ($n % 10 === 0) ? -1 * (($n % 25) + 1) : (($n * 7 + 3) % 90) + 1;
            $isOnline = ($n % 5 === 0);
            $mode = $n % 4;
            $ticketed = $mode === 0 || $mode === 1;
            $rsvp = $mode === 2;

            $alias = sprintf('demo-evt-%03d', $n + 1);
            if (isset($existingAliases[$alias])) {
                $created++;
                $n++;
                continue;
            }
            $this->makeDemoEvent($user, $ws, [
                'alias' => $alias, 'title' => $title,
                'location' => $isOnline ? 'Online' : ($city[0]),
                'start' => $now->copy()->addDays($dayOffset)->setTime(9 + ($n % 10), ($n % 4) * 15),
                'category' => $category,
                'online' => $isOnline, 'ticketed' => $ticketed, 'rsvp' => $rsvp,
                'hashtags' => $baseTags,
                'lat' => $isOnline ? null : $city[1], 'lng' => $isOnline ? null : $city[2],
                'clicks' => 15 + (($n * 13) % 220),
                'idx' => $n,
            ]);
            $created++;
            $n++;
        }
    }

    /**
     * Create one demo `ics` link + IcsData row (and, for ticketed events, a
     * couple of ticket tiers) from a normalized spec. Shared by the flagship
     * hand-authored events and the generated bulk batch so both paths stay
     * in lockstep (settings keys, gallery/cover image, hashtags).
     *
     * @param  array{alias:string,title:string,location:string,start:Carbon,category:string,online:bool,ticketed:bool,rsvp:bool,hashtags:array,lat:?float,lng:?float,clicks:int,idx:int} $spec
     */
    private function makeDemoEvent(User $user, Workspace $ws, array $spec): void
    {
        $clicks = $spec['clicks'];
        $unique = (int) max(1, round($clicks * 0.65));
        $isPast = $spec['start']->isPast();

        // updateOrCreate by alias (globally unique) rather than forceCreate,
        // so re-running the seeder — or resuming a partially-completed run —
        // never trips the aliases unique constraint.
        $link = Link::query()->withoutWorkspaceScope()->updateOrCreate(
            ['alias' => $spec['alias']],
            [
                'workspace_id' => $ws->id,
                'user_id' => $user->id,
                'created_by_user_id' => $user->id,
                'type' => 'ics', 'title' => $spec['title'],
                'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
                'total_clicks' => $clicks, 'unique_clicks' => $unique,
                'settings' => [
                    'event_category' => $spec['category'],
                    'is_online'      => $spec['online'],
                    'rsvp_enabled'   => $spec['rsvp'],
                ],
            ]
        );

        $seed = $spec['alias'];
        IcsData::updateOrCreate(
            ['link_id' => $link->id],
            [
                'event_name'  => $spec['title'],
                'description' => "Join us for {$spec['title']}" . ($spec['online'] ? ' — streamed online, link shared after RSVP.' : " in {$spec['location']}.") . ' ' . ($isPast ? 'Thanks to everyone who came out!' : "We can't wait to see you there."),
                'location'    => $spec['location'],
                'organizer'   => $user->name,
                'start_date'  => $spec['start'],
                'end_date'    => $spec['start']->copy()->addHours(2),
                'timezone'    => 'UTC',
                'latitude'    => $spec['lat'],
                'longitude'   => $spec['lng'],
                'hashtags'    => $spec['hashtags'],
                'cover_image_url' => "https://picsum.photos/seed/{$seed}/800/500",
                'gallery'     => [
                    "https://picsum.photos/seed/{$seed}-2/600/400",
                    "https://picsum.photos/seed/{$seed}-3/600/400",
                ],
            ]
        );

        EventTicketTier::where('link_id', $link->id)->delete();
        if ($spec['ticketed']) {
            $tierNames = $spec['idx'] % 3 === 0
                ? [['General Admission', 0, 200], ['VIP', 4500, 40]]
                : [['Early Bird', 1500, 30], ['Standard', 2500, 100]];
            foreach ($tierNames as $tIdx => [$tName, $priceCents, $capacity]) {
                $link->eventTicketTiers()->create([
                    'name' => $tName,
                    'price_cents' => $priceCents,
                    'currency' => 'usd',
                    'capacity' => $capacity,
                    'sold_count' => min($capacity, (int) round($capacity * (0.1 + ($spec['idx'] % 5) * 0.1))),
                    'is_active' => true,
                    'sort_order' => $tIdx,
                ]);
            }
        }
    }

    private function seedDigitalCards(User $user, Workspace $ws): void
    {
        $cards = [
            ['demo-vcf-mia',   'Mia',   'Garcia',  'NovaContent',     'Content Creator', 'mia@example.com',   '+1 415 555 0101'],
            ['demo-vcf-alex',  'Alex',  'Rivera',  'Rivera Coaching', 'Life Coach',      'alex@example.com',  '+1 415 555 0102'],
            ['demo-vcf-kai',   'Kai',   'Tanaka',  'Tanaka Studio',   'Photographer',    'kai@example.com',   '+81 3 5555 0103'],
            ['demo-vcf-lena',  'Lena',  'Schmidt', 'Cofoundery',      'Founder & CEO',   'lena@example.com',  '+49 30 555 0104'],
            ['demo-vcf-ravi',  'Ravi',  'Mehta',   'Spice Route',     'Head Chef',       'ravi@example.com',  '+1 212 555 0105'],
            ['demo-vcf-sara',  'Sara',  'Lin',     'GreenFuture',     'Program Director','sara@example.com',  '+1 415 555 0106'],
        ];
        foreach ($cards as $i => [$alias, $first, $last, $org, $title, $email, $phone]) {
            $link = Link::forceCreate([
                'workspace_id' => $ws->id,
                'user_id' => $user->id,
                'created_by_user_id' => $user->id,
                'type' => 'vcf', 'alias' => $alias,
                'title'   => "$first $last — $org",
                'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
                'total_clicks' => 18 + $i * 4,
                'unique_clicks' => 12 + $i * 3,
            ]);
            VcfData::forceCreate([
                'link_id' => $link->id, 'first_name' => $first, 'last_name' => $last,
                'organization' => $org, 'title' => $title, 'email' => $email, 'phone' => $phone,
                'website' => 'https://example.com', 'city' => 'San Francisco', 'country' => 'USA',
            ]);
        }
    }

    // ── Workspaces, members and task boards ─────────────────────────────

    /**
     * Seed a small pool of demo team-member accounts that the workspaces
     * below will invite as members. These are NOT creators (no biolinks
     * or feed posts) — they exist purely to populate workspace member lists,
     * task assignees and comment authors.
     *
     * @return array<int,User>
     */
    private function seedTeamMemberAccounts(): array
    {
        $personas = [
            ['demo-team-priya@1inme.com',   'Priya Patel',     'Designer'],
            ['demo-team-marco@1inme.com',   'Marco Rossi',     'Project Manager'],
            ['demo-team-yuki@1inme.com',    'Yuki Sato',       'Engineer'],
            ['demo-team-amelia@1inme.com',  'Amelia Cole',     'Writer'],
            ['demo-team-felix@1inme.com',   'Felix Mendez',    'Marketing Lead'],
            ['demo-team-noor@1inme.com',    'Noor Hassan',     'Operations'],
            ['demo-team-theo@1inme.com',    'Theo Whitfield',  'Support Lead'],
            ['demo-team-uma@1inme.com',     'Uma Nair',        'Analyst'],
        ];
        $members = [];
        foreach ($personas as [$email, $name, $persona]) {
            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $name,
                    'password' => Hash::make('password'),
                    'persona'  => $persona,
                    'is_demo'  => true,
                ]
            );
            $u->forceFill(['is_demo' => true, 'persona' => $persona])->save();
            // Auto-create their personal workspace too so member-side pages render.
            if (method_exists($u, 'ensureDefaultWorkspace')) {
                $u->ensureDefaultWorkspace();
            }
            $members[] = $u;
        }
        return $members;
    }

    /**
     * Create 4 team workspaces owned by the demo admin and invite a mix of
     * demo team-member accounts into each with varied roles. Returns the
     * new team workspaces (excluding the admin's personal one).
     *
     * @param  array<int,User> $teamMembers
     * @return array<int,Workspace>
     */
    private function seedTeamWorkspaces(User $admin, array $teamMembers): array
    {
        $specs = [
            ['Acme Studio',                ['admin', 'editor', 'editor', 'replier']],
            ['GreenFuture Team',           ['editor', 'analyst', 'viewer']],
            ['Indie Founders Collective',  ['admin', 'editor', 'replier', 'analyst', 'viewer']],
            ['Photo Crew',                 ['editor', 'editor', 'viewer']],
        ];

        $workspaces = [];
        $cursor = 0;
        foreach ($specs as [$name, $roles]) {
            $ws = Workspace::forceCreate([
                'owner_user_id' => $admin->id,
                'name'          => $name,
                'slug'          => 'demo-' . Str::slug($name) . '-' . Str::random(4),
                'is_personal'   => false,
            ]);

            foreach ($roles as $role) {
                $member = $teamMembers[$cursor % count($teamMembers)];
                $cursor++;
                WorkspaceMember::firstOrCreate(
                    ['workspace_id' => $ws->id, 'user_id' => $member->id],
                    ['role' => $role]
                );
            }

            // One pending invite per workspace so the "Pending invites"
            // section in the team UI has at least one row to show.
            WorkspaceInvite::forceCreate([
                'workspace_id'    => $ws->id,
                'inviter_user_id' => $admin->id,
                'email'           => 'demo-invite-' . Str::lower(Str::random(6)) . '@example.com',
                'role'            => 'editor',
                'token'           => Str::random(48),
                'expires_at'      => now()->addDays(7),
            ]);

            $workspaces[] = $ws;
        }
        return $workspaces;
    }

    /**
     * Create populated task boards for every demo workspace.
     *
     * @param  array<int,Workspace> $workspaces
     * @param  array<int,User>      $teamMembers  (used as assignees / commenters)
     */
    private function seedTaskBoards(array $workspaces, User $admin, array $teamMembers): void
    {
        $boardTemplates = [
            // Two boards per team workspace; one for the personal workspace.
            'team' => [
                ['name' => 'Roadmap',        'color' => '#8b5cf6'],
                ['name' => 'Content Calendar','color' => '#0ea5e9'],
            ],
            'personal' => [
                ['name' => 'Demo · My Tasks', 'color' => '#f59e0b'],
            ],
        ];

        $columnSpec = [
            ['name' => 'To Do',       'color' => '#64748b', 'is_done' => false],
            ['name' => 'In Progress', 'color' => '#3b82f6', 'is_done' => false],
            ['name' => 'Review',      'color' => '#a855f7', 'is_done' => false],
            ['name' => 'Done',        'color' => '#10b981', 'is_done' => true ],
        ];

        $cardSeeds = [
            ['Plan launch announcement',         'urgent', 0,  ['Launch', 'Marketing']],
            ['Draft pricing page copy',          'high',   30, ['Copy', 'Web']],
            ['Design new bio link template',     'high',   60, ['Design']],
            ['Set up analytics dashboard',       'normal', 80, ['Analytics']],
            ['Record short demo video',          'normal', 50, ['Video', 'Marketing']],
            ['Send Q3 newsletter',               'high',   20, ['Email']],
            ['Review September metrics',         'normal', 100,['Analytics']],
            ['Onboard new contractor',           'low',    40, ['Ops']],
            ['Refresh About page',               'normal', 10, ['Web', 'Copy']],
            ['Schedule team retrospective',      'low',    0,  ['Ops']],
            ['Audit broken short links',         'normal', 70, ['Maintenance']],
            ['Update press kit assets',          'low',    100,['Brand']],
        ];

        foreach ($workspaces as $ws) {
            $isPersonal = (bool) $ws->is_personal;
            $boards = $isPersonal ? $boardTemplates['personal'] : $boardTemplates['team'];

            foreach ($boards as $bIdx => $boardTpl) {
                $board = TaskBoard::forceCreate([
                    'workspace_id'       => $ws->id,
                    'created_by_user_id' => $admin->id,
                    'scope'              => $isPersonal ? 'personal' : 'team',
                    'owner_user_id'      => $isPersonal ? $admin->id : null,
                    'name'               => $boardTpl['name'],
                    'color'              => $boardTpl['color'],
                    'description'        => 'Demo board — ' . $boardTpl['name'],
                    'position'           => $bIdx + 1,
                ]);

                $columns = [];
                foreach ($columnSpec as $cIdx => $col) {
                    $columns[] = TaskColumn::forceCreate([
                        'workspace_id' => $ws->id,
                        'board_id'     => $board->id,
                        'name'         => $col['name'],
                        'color'        => $col['color'],
                        'position'     => $cIdx + 1,
                        'is_done'      => $col['is_done'],
                    ]);
                }

                // Build a label palette for this board (so card-label joins
                // can reference real label rows).
                $labelPalette = [
                    'Design'      => '#a855f7',
                    'Copy'        => '#f97316',
                    'Web'         => '#0ea5e9',
                    'Marketing'   => '#ec4899',
                    'Launch'      => '#ef4444',
                    'Email'       => '#22c55e',
                    'Analytics'   => '#14b8a6',
                    'Ops'         => '#64748b',
                    'Maintenance' => '#eab308',
                    'Brand'       => '#8b5cf6',
                    'Video'       => '#3b82f6',
                ];
                $labelRows = [];
                foreach ($labelPalette as $lname => $lcolor) {
                    $labelRows[$lname] = TaskLabel::forceCreate([
                        'workspace_id' => $ws->id,
                        'board_id'     => $board->id,
                        'name'         => $lname,
                        'color'        => $lcolor,
                    ]);
                }

                foreach ($cardSeeds as $cIdx => [$title, $priority, $progress, $labels]) {
                    $col = $columns[$cIdx % count($columns)];
                    $isDoneCol = $col->is_done;
                    $due = now()->copy()->addDays(($cIdx * 3) % 21 - 5)->startOfDay();

                    $card = TaskCard::forceCreate([
                        'workspace_id'       => $ws->id,
                        'board_id'           => $board->id,
                        'column_id'          => $col->id,
                        'created_by_user_id' => $admin->id,
                        'title'              => $title,
                        'description'        => "Demo task — {$title}. This card was created by the demo seeder so the board feels populated.",
                        'description_html'   => "<p>Demo task — <strong>{$title}</strong>.</p>",
                        'position'           => $cIdx + 1,
                        'due_date'           => $due,
                        'priority'           => $priority,
                        'progress'           => $isDoneCol ? 100 : $progress,
                        'completed_at'       => $isDoneCol ? now()->subDays($cIdx) : null,
                    ]);

                    // Assignees: rotate through the workspace's actual members
                    // (admin + every WorkspaceMember on this workspace) so the
                    // assignee chips are realistic.
                    $assignablePool = $isPersonal
                        ? [$admin]
                        : array_values(array_filter([
                            $admin,
                            ...array_map(
                                fn (WorkspaceMember $m) => $m->user,
                                $ws->members()->with('user')->get()->all()
                            ),
                        ]));
                    $assignCount = ($cIdx % 3) + 1;
                    for ($a = 0; $a < $assignCount && $a < count($assignablePool); $a++) {
                        $u = $assignablePool[($cIdx + $a) % count($assignablePool)];
                        if (!$u) continue;
                        DB::table('task_card_assignees')->updateOrInsert(
                            ['card_id' => $card->id, 'user_id' => $u->id],
                            ['created_at' => now(), 'updated_at' => now()],
                        );
                    }

                    // Labels — attach 1-2 from the card's label list.
                    foreach (array_slice($labels, 0, 2) as $lname) {
                        if (!isset($labelRows[$lname])) continue;
                        DB::table('task_card_labels')->updateOrInsert(
                            ['card_id' => $card->id, 'label_id' => $labelRows[$lname]->id],
                            ['created_at' => now(), 'updated_at' => now()],
                        );
                    }

                    // Subtasks (3 each, mixed completion).
                    $subs = ['Draft outline', 'Share with the team', 'Ship it'];
                    foreach ($subs as $sIdx => $stitle) {
                        TaskSubtask::forceCreate([
                            'workspace_id' => $ws->id,
                            'card_id'      => $card->id,
                            'title'        => $stitle,
                            'completed'    => $isDoneCol ? true : $sIdx === 0,
                            'position'     => $sIdx + 1,
                        ]);
                    }

                    // Comments — one from admin, one from a teammate (when available).
                    TaskComment::forceCreate([
                        'workspace_id' => $ws->id,
                        'card_id'      => $card->id,
                        'user_id'      => $admin->id,
                        'body'         => "Kicking this off — let's aim to have a v1 by Friday.",
                    ]);
                    if (!$isPersonal && !empty($teamMembers)) {
                        $teammate = $teamMembers[$cIdx % count($teamMembers)];
                        TaskComment::forceCreate([
                            'workspace_id' => $ws->id,
                            'card_id'      => $card->id,
                            'user_id'      => $teammate->id,
                            'body'         => "Got it — I'll pick this up after standup.",
                        ]);
                    }

                    // Activity log — one "created" entry per card so the
                    // sidebar history is non-empty.
                    TaskActivity::forceCreate([
                        'workspace_id' => $ws->id,
                        'card_id'      => $card->id,
                        'user_id'      => $admin->id,
                        'type'         => 'created',
                        'data'         => ['title' => $title],
                        'created_at'   => now()->subMinutes(($cIdx + 1) * 12),
                    ]);
                }
            }
        }
    }

    // ── Multi-creator content for the feed/discover experience ──────────

    /** @return array<int,User> */
    private function seedDemoCreators(): array
    {
        $personas = [
            ['mia',     'Mia Garcia',    'NovaContent · Content Creator',  '🎬 Vlogs about creative living, weekly drops.'],
            ['kai',     'Kai Tanaka',    'Photographer · Tokyo',           '📷 Street + portrait photography from Tokyo.'],
            ['lena',    'Lena Schmidt',  'Founder · Cofoundery',           '🚀 Building tools for indie founders. Behind the scenes.'],
            ['ravi',    'Ravi Mehta',    'Chef · Spice Route',             '🍜 Modern Indian recipes, supper-club drops.'],
            ['sara',    'Sara Lin',      'Director · GreenFuture',         '🌱 Climate action, field updates from reforestation projects.'],
            ['jordan',  'Jordan Reeves', 'Producer · LoFi Lab',            '🎧 Lo-fi beats, weekly mixtape & sample packs.'],
            ['olive',   'Olive Bennett', 'Illustrator · Studio Otter',     '🎨 Hand-printed posters and zines.'],
            ['rafael',  'Rafael Costa',  'Trainer · MoveStrong',           '💪 Bodyweight programs and recovery tips.'],
            ['hana',    'Hana Park',     'Novelist · Greylight Press',     '✍️ Slow fiction and reading lists.'],
            ['devon',   'Devon Walker',  'Podcaster · Build Notes',        '🎙️ Interviewing the people building the future.'],
            // Slideshow-background showcase profiles.
            ['lyric',   'Lyric Moreau',  'Visual Artist · Aurora Studio',  '🎨 Bold prints + behind-the-scenes from the studio.'],
            ['aurora',  'Aurora Patel',  'Travel Photographer',            '✈️ Slow travel and quiet places — fortnightly drops.'],
            // Conversational-mode showcase profiles.
            ['echo',    'Echo Nakamura', 'Coach · Echo Habits',            '🧘 1-1 habit coaching. Tap below for a free intro chat.'],
            ['pixel',   'Pixel Brooks',  'Brand Designer',                 '✨ Logo + identity work. Quick chat to brief me in.'],
            // Combined: conversational + slideshow background.
            ['novabot', 'Nova Lee',      'AI Sommelier · CellarChat',      '🍷 Personalised wine picks via a quick chat.'],
            // Video-background showcase profile.
            ['cinema',  'Cinema Holt',   'Filmmaker · Holt Pictures',      '🎥 Short films + behind-the-scenes from set.'],
            // Animated-template background showcase profile.
            ['nebula',  'Nebula Reyes',  'DJ · Aurora Nights',             '🌌 Late-night sets and ambient mixes.'],
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
            $u->forceFill([
                'persona' => $persona, 'bio' => $bio, 'handle' => $handle,
                'discoverable' => true, 'allow_followers' => true, 'is_demo' => true,
            ])->save();
            // Make sure each creator has a personal workspace so their
            // biolink + subscriber rows can hang off something concrete.
            if (method_exists($u, 'ensureDefaultWorkspace')) {
                $u->ensureDefaultWorkspace();
            }
            $creators[] = $u;
        }
        return $creators;
    }

    /** @param array<int,User> $creators */
    private function seedCreatorBiolinks(array $creators): void
    {
        $tiers = ['public', 'public', 'registered', 'followers', 'subscribers', 'public'];

        // Per-handle overrides for the new mode-showcase profiles. The
        // shape mirrors what the appearance editor (and ConversationFlow
        // toggle) writes to `settings.biolink` so the public renderer
        // picks them up without any code changes.
        $slideshowAlbums = [
            // Use existing public hero-role photos so we don't need new
            // uploads. The renderer just emits whatever string is stored
            // (see common/biolink.blade.php), so root-relative paths work.
            'lyric' => [
                '/images/hero-roles/role_artist.jpg',
                '/images/hero-roles/thumb_artwork.jpg',
                '/images/hero-roles/role_creator.jpg',
            ],
            'aurora' => [
                '/images/hero-roles/role_photographer.jpg',
                '/images/hero-roles/thumb_photo.jpg',
                '/images/hero-roles/role_influencer.jpg',
                '/images/hero-roles/role_creator.jpg',
            ],
            'novabot' => [
                '/images/hero-roles/role_business.jpg',
                '/images/hero-roles/role_coach.jpg',
                '/images/hero-roles/role_influencer.jpg',
            ],
        ];
        $modeOverrides = [
            'lyric'   => ['background_type' => 'slideshow', 'slideshow_interval' => 4],
            'aurora'  => ['background_type' => 'slideshow', 'slideshow_interval' => 6],
            'echo'    => ['mode' => 'conversational'],
            'pixel'   => ['mode' => 'conversational'],
            'novabot' => ['mode' => 'conversational', 'background_type' => 'slideshow', 'slideshow_interval' => 5],
            // Video background — uses a small remote-hosted MP4 plus a
            // poster from the existing hero-role art so the page still
            // renders something while the video buffers (or if the
            // remote source is offline). The renderer just emits these
            // strings into a <video><source>, so any reachable URL works.
            'cinema'  => [
                'background_type'   => 'video',
                'video_url'         => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'bg_fallback_image' => '/images/hero-roles/role_creator.jpg',
                'bg_fallback_color' => '#0a0612',
            ],
            // Animated CSS/JS template background — looked up by slug so
            // we don't hard-code an ID. Resolved below; falls back to a
            // gradient if the BgTemplateSeeder hasn't been run yet.
            'nebula'  => ['background_type' => 'template', 'bg_template_slug' => 'aurora-borealis'],
        ];

        // Resolve the template slug -> id once. If the BgTemplate seeder
        // hasn't been run (fresh DB), the override is dropped so the
        // creator still seeds cleanly with the default gradient.
        if (isset($modeOverrides['nebula']['bg_template_slug'])) {
            $tplSlug = $modeOverrides['nebula']['bg_template_slug'];
            $tpl = \App\Modules\Admin\Models\BgTemplate::where('slug', $tplSlug)->first();
            unset($modeOverrides['nebula']['bg_template_slug']);
            if ($tpl) {
                $modeOverrides['nebula']['bg_template_id'] = $tpl->id;
            } else {
                unset($modeOverrides['nebula']['background_type']);
            }
        }

        foreach ($creators as $i => $c) {
            $vis = $tiers[$i % count($tiers)];
            $override = $modeOverrides[$c->handle] ?? [];
            if (isset($slideshowAlbums[$c->handle])) {
                $override['slideshow_images'] = $slideshowAlbums[$c->handle];
            }
            // Mode-showcase profiles (slideshow background and/or
            // conversational mode) are forced to `public` visibility so
            // anonymous visitors can actually see the showcase render
            // without hitting the registered/followers/subscribers gate.
            if (isset($modeOverrides[$c->handle]) || isset($slideshowAlbums[$c->handle])) {
                $vis = 'public';
            }

            $page = [
                'alias'       => "demo-{$c->handle}",
                'title'       => $c->name,
                'heading'     => $c->name,
                'paragraph'   => $c->bio ?? 'Creator on Sayzio.',
                'visibility'  => $vis,
                'links'       => [
                    ['Latest on Instagram',  'https://www.instagram.com', 'fa-instagram'],
                    ['Subscribe',            'https://example.com/sub',   'fa-bell'],
                    ['Shop merch',           'https://example.com/shop',  'fa-shopping-bag'],
                ],
                'biolink_settings' => $override,
            ];
            $ws = Workspace::where('owner_user_id', $c->id)->where('is_personal', true)->first();
            $link = $this->createBiolink($c, $page, $ws);

            if (($override['mode'] ?? null) === 'conversational') {
                $this->seedConversationalFlow($link, $c);
            }
        }
    }

    /**
     * Seed a published ConversationFlow for a demo creator's biolink so
     * the public conversational renderer (`common.biolink-conversational`)
     * has something to drive immediately on the first visit.
     *
     * Cleanup is automatic: `conversation_flows.link_id` cascades on
     * delete, so wiping demo links in `wipeAllDemoContent` removes the
     * flow + steps + choices + actions + sessions in one shot.
     */
    private function seedConversationalFlow(Link $link, User $creator): void
    {
        $flow = ConversationFlow::create([
            'link_id'       => $link->id,
            'workspace_id'  => $link->workspace_id ?? null,
            'name'          => 'Demo Conversational Flow',
            'intro_message' => "Hey 👋 I'm {$creator->name}'s assistant — quick chat to point you the right way.",
            'is_published'  => true,
            'is_active'     => true,
            'version'       => 1,
            'settings'      => ['default_typing_ms' => 600],
        ]);

        $openShop = $flow->actions()->create([
            'kind'    => ConversationAction::KIND_OPEN_LINK,
            'label'   => 'Open shop',
            'payload' => ['url' => 'https://example.com/shop'],
        ]);
        $bookCall = $flow->actions()->create([
            'kind'    => ConversationAction::KIND_BOOK_CALENDAR,
            'label'   => 'Book a call',
            'payload' => ['booking_url' => 'https://cal.com/demo'],
        ]);
        $thanksMsg = $flow->actions()->create([
            'kind'    => ConversationAction::KIND_MESSAGE,
            'label'   => 'Thanks',
            'payload' => ['text' => "Thanks — I'll be in touch soon!"],
        ]);

        // Step 1 — entry: pick intent.
        $intent = $flow->steps()->create([
            'key'           => 'intent',
            'kind'          => ConversationStep::KIND_QUESTION,
            'message_text'  => "What brings you here today?",
            'answer_field'  => 'intent',
            'is_entry'      => true,
            'sort_order'    => 0,
            'settings'      => [],
        ]);
        $intent->choices()->createMany([
            ['label' => '🛒 Browse the shop', 'value' => 'shop',    'next_step_key' => 'shop_done', 'sort_order' => 0],
            ['label' => '📅 Book a session',  'value' => 'book',    'next_step_key' => 'ask_email', 'sort_order' => 1],
            ['label' => '👋 Just saying hi',  'value' => 'hi',      'next_step_key' => 'goodbye',   'sort_order' => 2],
        ]);

        // Step 2 — capture email (input) before booking.
        $flow->steps()->create([
            'key'           => 'ask_email',
            'kind'          => ConversationStep::KIND_INPUT,
            'message_text'  => "Awesome — what's the best email for the booking?",
            'answer_field'  => 'email',
            'sort_order'    => 1,
            'next_step_key' => 'book_done',
            'settings'      => ['input_kind' => 'email', 'placeholder' => 'you@example.com'],
        ]);

        // Step 3 — booking ending.
        $flow->steps()->create([
            'key'           => 'book_done',
            'kind'          => ConversationStep::KIND_END,
            'message_text'  => "Perfect — tap below to pick a time.",
            'sort_order'    => 2,
            'action_id'     => $bookCall->id,
        ]);

        // Step 4 — shop ending.
        $flow->steps()->create([
            'key'           => 'shop_done',
            'kind'          => ConversationStep::KIND_END,
            'message_text'  => "Here's the latest drop 👇",
            'sort_order'    => 3,
            'action_id'     => $openShop->id,
        ]);

        // Step 5 — friendly goodbye.
        $flow->steps()->create([
            'key'           => 'goodbye',
            'kind'          => ConversationStep::KIND_END,
            'message_text'  => "Thanks for stopping by ✨",
            'sort_order'    => 4,
            'action_id'     => $thanksMsg->id,
        ]);
    }

    /** Create 50+ feed_events per creator across all visibility tiers. */
    private function seedFeedPosts(array $creators): void
    {
        $tiers = ['public', 'registered', 'followers', 'subscribers'];
        $kinds = [
            ['🎬 Behind the scenes from this week\'s shoot.',   'photo'],
            ['🔥 New project just dropped — link in bio.',      'launch'],
            ['💌 Quick update for my supporters — thank you!',  'update'],
            ['🎁 Subscriber-only sneak peek of what\'s next.',  'gift'],
            ['📅 New session opens tomorrow at 9am.',           'event'],
            ['📸 Just posted a new gallery, take a look.',      'post'],
            ['🎙️ New episode is live — tap to listen.',         'audio'],
            ['📝 Long read: lessons from the past quarter.',    'article'],
            ['🛍️ Restocked the shop — limited quantities.',    'shop'],
            ['🌍 On the road this week — full route inside.',  'travel'],
        ];
        $subjectTypes = ['demo', 'biolink', 'short_link', 'event', 'post'];

        foreach ($creators as $i => $c) {
            $rows = [];
            // 55 events per creator, spread across the past ~12 weeks so the
            // discover feed has plenty of recent + older entries to show.
            for ($j = 0; $j < 55; $j++) {
                [$body, $kind] = $kinds[$j % count($kinds)];
                $rows[] = [
                    'user_id'      => $c->id,
                    'type'         => 'creator_post',
                    'subject_id'   => null,
                    'subject_type' => $subjectTypes[($i + $j) % count($subjectTypes)],
                    'data'         => json_encode([
                        'body' => $body . ' (' . $c->name . ' #' . ($j + 1) . ')',
                        'kind' => $kind,
                    ]),
                    'visibility'   => $tiers[($i + $j) % count($tiers)],
                    'is_demo'      => true,
                    'occurred_at'  => Carbon::now()
                        ->subDays(($j * 1.5) + ($i * 0.4))
                        ->subHours(($j * 3) % 24),
                ];
            }
            // Bulk insert in chunks so we don't generate 550+ separate
            // INSERTs across the 10 creators.
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('feed_events')->insert($chunk);
            }
        }
    }

    /** Demo admin follows every creator + creators follow each other. */
    private function seedFollowsAndSubscriptions(User $admin, array $creators): void
    {
        foreach ($creators as $c) {
            Follow::firstOrCreate([
                'follower_id' => $admin->id,
                'creator_id'  => $c->id,
            ], ['created_at' => now()]);

            // Demo admin subscribes (email) to each creator so subscriber-only
            // content has at least one recipient to demonstrate access.
            $cWs = Workspace::where('owner_user_id', $c->id)->where('is_personal', true)->first();
            Subscriber::firstOrCreate(
                ['user_id' => $c->id, 'type' => 'email', 'email' => $admin->email],
                [
                    'workspace_id' => $cWs?->id,
                    'status'       => 'active',
                ]
            );
        }

        // Cross-follows so the "creators you follow also follow…" widget
        // and discover graph aren't empty. Each creator follows the next 3
        // in the list (wrapping around).
        $count = count($creators);
        foreach ($creators as $i => $c) {
            for ($k = 1; $k <= 3; $k++) {
                $target = $creators[($i + $k) % $count];
                if ($target->id === $c->id) continue;
                Follow::firstOrCreate([
                    'follower_id' => $c->id,
                    'creator_id'  => $target->id,
                ], ['created_at' => now()->subDays($k)]);
            }
        }
    }
}
