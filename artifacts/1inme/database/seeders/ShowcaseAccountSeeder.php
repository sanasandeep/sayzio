<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersona;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlide;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\ReviewProvider;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VcfData;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Task #3489 — provisions a single, fully-loaded showcase/demo account
 * (`sana@sayzio.app`) on the internal "Unlimited" comp plan with admin +
 * super-admin access, a fresh main link-in-bio page, two realistic demo
 * links plus one usage-explainer page for every `links.type`, a biolink
 * page covering every widget/block type, every other feature surface
 * populated (forms, QR studio, subscribers, reviews, Buzz, contacts) and
 * 90 days of backdated analytics (clicks / sessions / heatmap / rollups)
 * so the account is a ready-made walkthrough of the whole product.
 *
 * Also runnable via the guarded `php artisan showcase:seed` command
 * ({@see \App\Console\Commands\SeedShowcaseAccount}).
 *
 * Strictly additive/idempotent: everything is scoped to this one fixed
 * email and wiped-then-rebuilt on every run (never touches any other
 * account's rows). Safe to call repeatedly via
 * `php artisan db:seed --class=ShowcaseAccountSeeder`.
 *
 * No live third-party calls, no real payments — all review providers /
 * payout-style blocks are seeded in "preview" state with static sample data.
 */
class ShowcaseAccountSeeder extends Seeder
{
    public const EMAIL = 'sana@sayzio.app';
    public const PASSWORD = 'DiaryLabs@1906';
    public const HANDLE = 'sanashowcase';
    public const NAME = 'Sana Rahman';
    public const BIO = 'Full-product showcase account: every Sayzio feature, populated end to end.';

    private User $user;
    private Workspace $workspace;

    /**
     * Whether this account should be provisioned as a read-only demo
     * (Task #3498): `is_readonly_demo = true` on the user row, which the
     * global write-guard middleware uses to block every state-changing
     * request from this account. Overridden by {@see ReadonlyDemoAccountSeeder}.
     */
    protected function isReadonlyDemo(): bool
    {
        return false;
    }

    /**
     * Whether this account should be granted the privileged `user-admin`
     * web role (user-side admin surfaces such as role/plan management).
     * Overridden to `false` by {@see ReadonlyDemoAccountSeeder} — a
     * publicly-safe demo account must be a plain user with no elevated
     * privileges of any kind (Task #3498).
     */
    protected function shouldAssignUserAdminRole(): bool
    {
        return true;
    }

    public function run(): void
    {
        $plan = Plan::where('slug', 'unlimited')->first();
        if (!$plan) {
            $this->command?->warn('ShowcaseAccountSeeder: "unlimited" plan not found; run PlansAndAddonsSeeder first. Skipping.');
            return;
        }

        $t0 = microtime(true);
        $lap = function (string $label) use (&$t0) {
            $now = microtime(true);
            $this->command?->getOutput()?->writeln(sprintf('  [%6.2fs] %s', $now - $t0, $label));
            $t0 = $now;
        };

        $this->user = $this->ensureUser($plan);
        $lap('ensureUser');
        $this->ensureAdminBridge($this->user);
        $lap('ensureAdminBridge');
        $this->wipeShowcaseContent($this->user);
        $lap('wipeShowcaseContent');
        $this->workspace = $this->user->ensureDefaultWorkspace();
        $lap('ensureDefaultWorkspace');

        // Bind the active workspace for the duration of this seeder run so
        // every BelongsToWorkspace model created below (Link, Form, QrCode,
        // SocialProof, Subscriber, Contact, FormSubmission, ...) auto-fills
        // workspace_id via its `creating` hook — without this, CLI-created
        // rows land with workspace_id = null and are invisible to the
        // showcase account once it logs in with an active workspace.
        app()->instance('current_workspace', $this->workspace);

        $links = [];
        $links['url'] = $this->seedUrlLinks();
        $lap('seedUrlLinks');
        $links['file'] = $this->seedFileLinks();
        $lap('seedFileLinks');
        $links['ics'] = $this->seedIcsLinks();
        $lap('seedIcsLinks');
        $links['vcf'] = $this->seedVcfLinks();
        $lap('seedVcfLinks');
        $links['biolink'] = $this->seedBiolinks();
        $lap('seedBiolinks');
        $links['slides'] = $this->seedSlideLinks();
        $lap('seedSlideLinks');
        $links['restaurant_menu'] = $this->seedRestaurantMenus();
        $lap('seedRestaurantMenus');
        $links['store_menu'] = $this->seedStoreMenus();
        $lap('seedStoreMenus');
        $links['service_booking'] = $this->seedServiceBookings();
        $lap('seedServiceBookings');
        $links['resume'] = $this->seedResumes();
        $lap('seedResumes');
        $links['calendar'] = $this->seedCalendars();
        $lap('seedCalendars');
        $links['paid_page'] = $this->seedPaidPages();
        $lap('seedPaidPages');
        $links['reviews'] = $this->seedReviewLinks();
        $lap('seedReviewLinks');
        $links['brand_kit'] = $this->seedBrandKits();
        $lap('seedBrandKits');
        $links['ai_chat'] = $this->seedAiChatLinks();
        $lap('seedAiChatLinks');
        $links['conversational'] = $this->seedConversationalLinks();
        $lap('seedConversationalLinks');

        $this->seedExplainerLinks();
        $lap('seedExplainerLinks');
        $this->seedMainBioPage();
        $lap('seedMainBioPage');

        $form = $this->seedForms();
        $lap('seedForms');
        $socialProof = $this->seedBuzz();
        $lap('seedBuzz');
        $this->seedWidgetCatalogBiolink($form, $socialProof);
        $lap('seedWidgetCatalogBiolink');

        $this->seedQrCodes($links['url']);
        $lap('seedQrCodes');
        $this->seedSubscribers($links['biolink'][0] ?? null);
        $lap('seedSubscribers');
        $this->seedReviewsAndProviders($links['reviews'][0] ?? null);
        $lap('seedReviewsAndProviders');
        $this->seedContacts();
        $lap('seedContacts');

        $allLinkIds = Link::where('user_id', $this->user->id)->pluck('id')->all();

        $this->seedAnalytics($allLinkIds);
        $lap('seedAnalytics');

        $this->command?->info(sprintf(
            'Showcase account ready: %s (%d links across 16 types: 2 demos + 1 explainer per type, fresh main bio page, widget catalog, 90 days of backdated analytics).',
            static::EMAIL,
            count($allLinkIds)
        ));

        // Don't leak the workspace binding into whatever else runs in this
        // PHP process afterwards (e.g. other seeders in the same
        // DatabaseSeeder pass, or a test suite bootstrapping multiple
        // seeders back to back).
        app()->forgetInstance('current_workspace');
    }

    /**
     * Backdated analytics backfill for the showcase account. {@see run()}
     * already calls this as part of the standard, one-shot provisioning
     * pass; this public wrapper exists only so analytics can also be
     * regenerated on their own (e.g. to refresh the backdated window)
     * without re-running the full wipe-and-rebuild of the rest of the
     * account's content.
     */
    public function seedAnalyticsForShowcaseUser(): void
    {
        $user = User::where('email', static::EMAIL)->first();
        if (!$user) {
            $this->command?->warn('ShowcaseAccountSeeder: account not found; run the main seeder first.');
            return;
        }

        $linkIds = Link::where('user_id', $user->id)->pluck('id')->all();
        $this->seedAnalytics($linkIds);
        $this->command?->info('Showcase analytics backfilled for ' . count($linkIds) . ' links.');
    }

    // ── Account provisioning ────────────────────────────────────────────

    private function ensureUser(Plan $plan): User
    {
        $farFuture = now()->addYears(10);

        $user = User::updateOrCreate(
            ['email' => static::EMAIL],
            [
                'name' => static::NAME,
                'password' => Hash::make(static::PASSWORD),
                'handle' => static::HANDLE,
                'bio' => static::BIO,
                'status' => 'active',
                'plan_id' => $plan->id,
                'billing_cycle' => 'annual',
                'plan_expires_at' => $farFuture,
                'comp_plan_expires_at' => $farFuture,
                'timezone' => 'UTC',
                'language' => 'en',
                'discoverable' => true,
                'email_verified_at' => now(),
                'onboarded_at' => now(),
                'is_demo' => true,
                'is_readonly_demo' => $this->isReadonlyDemo(),
            ]
        );

        $userAdminRoleId = DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            if ($this->shouldAssignUserAdminRole()) {
                $user->roles()->syncWithoutDetaching([$userAdminRoleId]);
            } else {
                // Idempotent cleanup: if this account previously had the
                // privileged role attached (e.g. from a run before this
                // guard existed), strip it so a publicly-safe demo account
                // is never left with elevated user-side privileges.
                $user->roles()->detach($userAdminRoleId);
            }
            $user->flushPermissionCache();
        }

        return $user->fresh();
    }

    /**
     * Bridges this user account to a back-office {@see Admin} record with
     * super-admin access. Overridden as a no-op by
     * {@see ReadonlyDemoAccountSeeder} so that account never gets admin or
     * super-admin access (Task #3498, step 3).
     */
    protected function ensureAdminBridge(User $user): void
    {
        $superAdminRoleId = Role::where('slug', 'super-admin')->where('guard', 'admin')->value('id');
        if (!$superAdminRoleId) {
            return;
        }

        Admin::updateOrCreate(
            ['email' => static::EMAIL],
            [
                'name' => $user->name,
                'password' => Hash::make(static::PASSWORD),
                'role_id' => $superAdminRoleId,
                'status' => 'active',
            ]
        );
    }

    /** Wipe every row this seeder previously created for this one account. */
    private function wipeShowcaseContent(User $user): void
    {
        $linkIds = Link::where('user_id', $user->id)->pluck('id')->all();
        if ($linkIds) {
            DB::table('link_clicks')->whereIn('link_id', $linkIds)->delete();
            DB::table('page_sessions')->whereIn('link_id', $linkIds)->delete();
            DB::table('link_click_daily')->whereIn('link_id', $linkIds)->delete();
            DB::table('link_click_daily_dimensions')->whereIn('link_id', $linkIds)->delete();
        }

        // These satellite tables key off link_id/menu_id/calendar_id with no
        // DB-level FK cascade, so clean them up explicitly before dropping
        // the links/resumes rows themselves. Subqueries keep this to one
        // round trip per statement instead of pluck-then-delete pairs.
        DB::table('restaurant_order_items')->whereIn('order_id', function ($q) use ($user) {
            $q->select('id')->from('restaurant_orders')->whereIn('menu_id', function ($q2) use ($user) {
                $q2->select('id')->from('restaurant_menus')->where('user_id', $user->id);
            });
        })->delete();
        DB::table('restaurant_orders')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('restaurant_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('restaurant_tables')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('restaurant_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('restaurant_menu_items')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('restaurant_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('restaurant_menu_categories')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('restaurant_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('restaurant_menus')->where('user_id', $user->id)->delete();

        DB::table('store_order_items')->whereIn('order_id', function ($q) use ($user) {
            $q->select('id')->from('store_orders')->whereIn('menu_id', function ($q2) use ($user) {
                $q2->select('id')->from('store_menus')->where('user_id', $user->id);
            });
        })->delete();
        DB::table('store_orders')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('store_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('store_products')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('store_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('store_categories')->whereIn('menu_id', function ($q) use ($user) {
            $q->select('id')->from('store_menus')->where('user_id', $user->id);
        })->delete();
        DB::table('store_menus')->where('user_id', $user->id)->delete();

        DB::table('calendar_events')->whereIn('calendar_id', function ($q) use ($user) {
            $q->select('id')->from('calendars')->where('user_id', $user->id);
        })->delete();
        DB::table('calendar_follows')->whereIn('calendar_id', function ($q) use ($user) {
            $q->select('id')->from('calendars')->where('user_id', $user->id);
        })->delete();
        DB::table('calendars')->where('user_id', $user->id)->delete();

        // resumes has no link_id column; child rows cascade off resume_id.
        Resume::where('user_id', $user->id)->delete();

        Link::where('user_id', $user->id)->delete();

        Form::where('user_id', $user->id)->delete();
        QrCode::where('user_id', $user->id)->delete();
        SocialProof::where('user_id', $user->id)->delete();
        Subscriber::where('user_id', $user->id)->delete();
        ReviewProvider::where('user_id', $user->id)->delete();
        Review::where('user_id', $user->id)->delete();
        BrandKit::where('user_id', $user->id)->delete();
        AiCompanion::where('user_id', $user->id)->delete();
        AiPersona::where('user_id', $user->id)->delete();
        AiPersonaAgent::where('user_id', $user->id)->delete();

        $contactIds = Contact::where('user_id', $user->id)->pluck('id')->all();
        if ($contactIds) {
            DB::table('contact_phones')->whereIn('contact_id', $contactIds)->delete();
            DB::table('contact_emails')->whereIn('contact_id', $contactIds)->delete();
        }
        Contact::where('user_id', $user->id)->delete();
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    private function makeLink(string $type, string $aliasSuffix, string $title, array $extra = []): Link
    {
        return Link::create(array_merge([
            'user_id' => $this->user->id,
            'type' => $type,
            'alias' => static::HANDLE . '-' . $aliasSuffix,
            'title' => $title,
            'is_active' => true,
            'visibility' => 'public',
            'is_demo' => true,
        ], $extra));
    }

    private function img(string $seed, int $w = 800, int $h = 600): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    // ── Link type: url ───────────────────────────────────────────────────

    /** @return Link[] */
    private function seedUrlLinks(): array
    {
        $defs = [
            ['product-launch', 'Product Launch Announcement', 'https://sayzio.example.com/blog/product-launch'],
            ['event-signup', 'Community Event Signup', 'https://sayzio.example.com/events/community-meetup'],
        ];
        $out = [];
        foreach ($defs as $i => [$suffix, $title, $url]) {
            $out[] = $this->makeLink('url', "url-{$suffix}", $title, [
                'long_url' => $url,
                'utm_source' => 'showcase',
                'utm_medium' => 'demo',
                'utm_campaign' => 'sana-showcase-' . ($i + 1),
            ]);
        }
        return $out;
    }

    // ── Link type: file ──────────────────────────────────────────────────

    /** @return Link[] */
    private function seedFileLinks(): array
    {
        $defs = [
            ['media-kit', 'Media Kit PDF', 'media-kit.pdf', 'application/pdf', 245_000],
            ['pricing-sheet', 'Pricing Sheet', 'pricing-sheet.pdf', 'application/pdf', 128_000],
        ];
        $out = [];
        foreach ($defs as [$suffix, $title, $filename, $mime, $size]) {
            $link = $this->makeLink('file', "file-{$suffix}", $title);
            FileLink::create([
                'link_id' => $link->id,
                'original_name' => $filename,
                'stored_path' => "showcase/{$this->user->id}/{$filename}",
                'mime_type' => $mime,
                'file_size' => $size,
                'download_count' => 0,
                'disk' => 'public',
                'show_download_page' => true,
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: ics ───────────────────────────────────────────────────

    /** @return Link[] */
    private function seedIcsLinks(): array
    {
        $defs = [
            ['webinar', 'Product Webinar', now()->addDays(10), 'Live Webinar'],
            ['workshop', 'Design Workshop', now()->addDays(20), 'In-Person Workshop'],
        ];
        $out = [];
        foreach ($defs as [$suffix, $title, $start, $eventName]) {
            $link = $this->makeLink('ics', "ics-{$suffix}", $title);
            IcsData::create([
                'link_id' => $link->id,
                'event_name' => $eventName,
                'description' => "Join us for the {$eventName}, a showcase demo event.",
                'location' => 'Online / Sayzio HQ',
                'organizer' => $this->user->name,
                'organizer_email' => static::EMAIL,
                'start_date' => $start,
                'end_date' => (clone $start)->addHours(2),
                'timezone' => 'UTC',
                'all_day' => false,
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: vcf ───────────────────────────────────────────────────

    /** @return Link[] */
    private function seedVcfLinks(): array
    {
        $defs = [
            ['personal', 'Personal Contact Card', 'Sana', 'Rahman', 'Sayzio Showcase'],
            ['work', 'Work Contact Card', 'Sana', 'Rahman', 'Sayzio Inc.'],
        ];
        $out = [];
        foreach ($defs as [$suffix, $title, $first, $last, $org]) {
            $link = $this->makeLink('vcf', "vcf-{$suffix}", $title);
            VcfData::create([
                'link_id' => $link->id,
                'first_name' => $first,
                'last_name' => $last,
                'organization' => $org,
                'title' => 'Showcase Contact',
                'email' => static::EMAIL,
                'phone' => '+1 555 010 1000',
                'website' => 'https://sayzio.example.com',
                'city' => 'San Francisco',
                'state' => 'CA',
                'country' => 'US',
                'note' => 'Seeded via ShowcaseAccountSeeder.',
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: biolink (3 examples, incl. the widget-catalog page) ──

    /** @return Link[] */
    private function seedBiolinks(): array
    {
        $personal = $this->makeLink('biolink', 'bio-personal', 'Sana: Personal Bio');
        $this->blocks($personal, [
            ['heading', ['mode' => 'with_logo']],
            ['avatar', []],
            ['paragraph_rich', []],
            ['socials', []],
            ['link', ['_mode' => 'standard']],
            ['link', ['_mode' => 'featured']],
        ]);

        $business = $this->makeLink('biolink', 'bio-business', 'Sana: Business Bio');
        $this->blocks($business, [
            ['heading', []],
            ['profile_card', ['_layout' => 'cover']],
            ['product', []],
            ['testimonials', []],
            ['contact_form', []],
            ['map', []],
        ]);

        $catalog = $this->makeLink('biolink', 'bio-widget-catalog', 'Widget Catalog: Every Block Type');

        return [$personal, $business, $catalog];
    }

    private function blocks(Link $link, array $defs): void
    {
        $now = now();
        $rows = [];
        foreach ($defs as $i => [$type, $overrides]) {
            $content = BlockDefaults::withoutAdminOverrides(fn () => BlockDefaults::contentForType($type));
            $style = BlockDefaults::withoutAdminOverrides(fn () => BlockDefaults::styleForType($type));
            $rows[] = [
                'link_id' => $link->id,
                'type' => $type,
                'settings' => json_encode(array_merge($content, $overrides, ['_style' => $style])),
                'sort_order' => $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('biolink_blocks')->insert($rows);
        }
    }

    /** One block for every canonical (non-alias, non-system) picker type. */
    private function seedWidgetCatalogBiolink($form, $socialProof): void
    {
        $link = Link::where('user_id', $this->user->id)
            ->where('type', 'biolink')
            ->where('alias', static::HANDLE . '-bio-widget-catalog')
            ->first();
        if (!$link) {
            return;
        }

        $types = array_keys(array_filter(
            BiolinkBlock::pickerTypes(),
            fn ($meta) => empty($meta['system'])
        ));

        $sort = 0;
        $rows = [];
        foreach ($types as $type) {
            $content = BlockDefaults::withoutAdminOverrides(fn () => BlockDefaults::contentForType($type));
            $style = BlockDefaults::withoutAdminOverrides(fn () => BlockDefaults::styleForType($type));

            if ($type === 'form' && $form) {
                $content['form_id'] = $form->id;
            }
            if ($type === 'social_proof' && $socialProof) {
                $content['social_proof_id'] = $socialProof->id;
            }

            $rows[] = [
                'link_id' => $link->id,
                'type' => $type,
                'settings' => json_encode(array_merge($content, ['_style' => $style])),
                'sort_order' => $sort++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 40) as $chunk) {
            DB::table('biolink_blocks')->insert($chunk);
        }
    }

    // ── Link type: slides ────────────────────────────────────────────────

    /** @return Link[] */
    private function seedSlideLinks(): array
    {
        $titles = [
            ['story', 'Our Story', ['How it started', 'Why we built Sayzio', 'What comes next']],
            ['case-study', 'Customer Case Study', ['The challenge', 'Our solution', 'The results']],
        ];
        $out = [];
        foreach ($titles as [$suffix, $title, $slideTitles]) {
            $link = $this->makeLink('slides', "slides-{$suffix}", $title);
            $deck = LinkSlideDeck::create([
                'link_id' => $link->id,
                'workspace_id' => $this->workspace->id,
                'version' => 1,
                'is_published' => true,
                'settings' => ['theme' => 'dark'],
            ]);
            $slideRows = [];
            foreach ($slideTitles as $i => $slideTitle) {
                $slideRows[] = [
                    'deck_id' => $deck->id,
                    'sort_order' => $i,
                    'title' => $slideTitle,
                    'background' => json_encode(['type' => 'color', 'value' => '#0f0f1a']),
                    'settings' => json_encode(['text' => "Placeholder content for \"{$slideTitle}\"."]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('link_slides')->insert($slideRows);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: restaurant_menu ───────────────────────────────────────

    /** @return Link[] */
    private function seedRestaurantMenus(): array
    {
        $defs = ['downtown', 'rooftop'];
        $out = [];
        foreach ($defs as $i => $suffix) {
            $link = $this->makeLink('restaurant_menu', "menu-{$suffix}", 'Restaurant Menu: ' . Str::headline($suffix));
            $menu = RestaurantMenu::create([
                'link_id' => $link->id,
                'user_id' => $this->user->id,
                'mode' => 'order',
                'currency' => 'USD',
                'accent_color' => '#7c3aed',
                'settings' => ['tax' => ['enabled' => true, 'rate' => 8.5, 'inclusive' => false]],
            ]);
            $catRows = [];
            foreach (['Starters', 'Mains'] as $ci => $catName) {
                $catRows[] = [
                    'menu_id' => $menu->id, 'name' => $catName, 'sort_order' => $ci, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('restaurant_menu_categories')->insert($catRows);
            $cats = RestaurantMenuCategory::where('menu_id', $menu->id)->pluck('id', 'name');

            $itemRows = [];
            foreach (['Starters', 'Mains'] as $catName) {
                for ($ii = 1; $ii <= 2; $ii++) {
                    $itemRows[] = [
                        'menu_id' => $menu->id,
                        'category_id' => $cats[$catName],
                        'name' => "{$catName} Item {$ii}",
                        'description' => 'A showcase menu item.',
                        'price' => 9.99 + $ii + $i,
                        'currency' => 'USD',
                        'photo_url' => $this->img("resto-{$suffix}-{$catName}-{$ii}", 400, 300),
                        'sort_order' => $ii,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            DB::table('restaurant_menu_items')->insert($itemRows);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: store_menu ────────────────────────────────────────────

    /** @return Link[] */
    private function seedStoreMenus(): array
    {
        $defs = ['apparel', 'accessories'];
        $out = [];
        foreach ($defs as $i => $suffix) {
            $link = $this->makeLink('store_menu', "store-{$suffix}", 'Store: ' . Str::headline($suffix));
            $menu = StoreMenu::create([
                'link_id' => $link->id,
                'user_id' => $this->user->id,
                'mode' => 'order',
                'currency' => 'USD',
                'accent_color' => '#10b981',
                'settings' => ['accepting_orders' => true],
            ]);
            $catRows = [];
            foreach (['Featured', 'New Arrivals'] as $ci => $catName) {
                $catRows[] = [
                    'menu_id' => $menu->id, 'name' => $catName, 'sort_order' => $ci, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('store_categories')->insert($catRows);
            $cats = StoreCategory::where('menu_id', $menu->id)->pluck('id', 'name');

            $productRows = [];
            foreach (['Featured', 'New Arrivals'] as $catName) {
                for ($ii = 1; $ii <= 2; $ii++) {
                    $productRows[] = [
                        'menu_id' => $menu->id,
                        'category_id' => $cats[$catName],
                        'name' => "{$catName} Product {$ii}",
                        'description' => 'A showcase store product.',
                        'price' => 19.99 + $ii + $i,
                        'currency' => 'USD',
                        'photo_url' => $this->img("store-{$suffix}-{$catName}-{$ii}", 400, 400),
                        'sort_order' => $ii,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            DB::table('store_products')->insert($productRows);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: service_booking ───────────────────────────────────────

    /** @return Link[] */
    private function seedServiceBookings(): array
    {
        $defs = [
            ['consult', 'Strategy Consultation', 30],
            ['design-review', 'Design Review Session', 45],
        ];
        $out = [];
        foreach ($defs as [$suffix, $title, $slot]) {
            $link = $this->makeLink('service_booking', "booking-{$suffix}", $title);
            ServiceBooking::create([
                'link_id' => $link->id,
                'user_id' => $this->user->id,
                'mode' => 'booking',
                'currency' => 'USD',
                'accent_color' => '#f59e0b',
                'slot_length_minutes' => $slot,
                'lead_time_minutes' => 60,
                'max_days_ahead' => 30,
                'timezone' => 'UTC',
                'settings' => [
                    'services' => [
                        ['name' => $title, 'duration_minutes' => $slot, 'price' => 0],
                    ],
                ],
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: resume ────────────────────────────────────────────────

    /** @return Link[] */
    private function seedResumes(): array
    {
        $defs = ['product-designer', 'growth-marketer'];
        $out = [];
        foreach ($defs as $i => $suffix) {
            $link = $this->makeLink('resume', "resume-{$suffix}", 'Resume: ' . Str::headline($suffix));
            $resume = Resume::create([
                'user_id' => $this->user->id,
                'name' => 'Resume: ' . Str::headline($suffix),
                'slug' => static::HANDLE . '-resume-' . $suffix,
                'is_public' => true,
                'visibility' => 'public',
                'is_default' => $i === 0,
            ]);
            $link->update(['resume_id' => $resume->id]);

            DB::table('resume_section_items')->insert([
                [
                    'resume_id' => $resume->id, 'section_type' => 'summary', 'position' => 0,
                    'data' => json_encode(['text' => 'Showcase resume generated for the Sayzio demo account.']),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'resume_id' => $resume->id, 'section_type' => 'experience', 'position' => 1,
                    'data' => json_encode(['title' => 'Senior Role', 'company' => 'Sayzio Inc.', 'start' => '2022', 'end' => 'Present']),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'resume_id' => $resume->id, 'section_type' => 'skills', 'position' => 2,
                    'data' => json_encode(['skills' => ['Product Strategy', 'Design Systems', 'Growth']]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: calendar ──────────────────────────────────────────────

    /** @return Link[] */
    private function seedCalendars(): array
    {
        $defs = ['events', 'office-hours'];
        $out = [];
        foreach ($defs as $i => $suffix) {
            $link = $this->makeLink('calendar', "cal-{$suffix}", 'Calendar: ' . Str::headline($suffix));
            $calendar = Calendar::create([
                'link_id' => $link->id,
                'user_id' => $this->user->id,
                'title' => 'Calendar: ' . Str::headline($suffix),
                'slug' => static::HANDLE . '-cal-' . $suffix,
                'description' => 'Showcase calendar of upcoming events.',
                'timezone' => 'UTC',
                'is_public' => true,
            ]);
            $eventRows = [];
            for ($e = 1; $e <= 3; $e++) {
                $eventRows[] = [
                    'calendar_id' => $calendar->id,
                    'user_id' => $this->user->id,
                    'title' => "Event {$e}: " . Str::headline($suffix),
                    'description' => 'A showcase calendar event.',
                    'start_at' => now()->addDays($i * 5 + $e),
                    'end_at' => now()->addDays($i * 5 + $e)->addHours(1),
                    'timezone' => 'UTC',
                    'location' => 'Online',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('calendar_events')->insert($eventRows);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: paid_page ─────────────────────────────────────────────

    /** @return Link[] */
    private function seedPaidPages(): array
    {
        $defs = ['creator-hub', 'membership'];
        $out = [];
        foreach ($defs as $suffix) {
            $out[] = $this->makeLink('paid_page', "paid-{$suffix}", 'Paid Page: ' . Str::headline($suffix), [
                'settings' => [
                    'paid_page' => [
                        'id' => 'classic',
                        'name' => 'Classic',
                        'tagline' => 'Exclusive posts, tiers & tips, all in one place.',
                        'accent' => '#7c3aed',
                        'text' => '#f8fafc',
                        'card_bg' => 'rgba(255,255,255,0.06)',
                        'radius' => '16px',
                        'font' => 'Space Grotesk',
                        'motion' => true,
                    ],
                ],
            ]);
        }
        return $out;
    }

    // ── Link type: reviews ───────────────────────────────────────────────

    /** @return Link[] */
    private function seedReviewLinks(): array
    {
        $defs = ['product', 'service'];
        $out = [];
        foreach ($defs as $suffix) {
            $out[] = $this->makeLink('reviews', "reviews-{$suffix}", 'Reviews: ' . Str::headline($suffix));
        }
        return $out;
    }

    // ── Link type: brand_kit ─────────────────────────────────────────────

    /** @return Link[] */
    private function seedBrandKits(): array
    {
        $defs = [
            ['main', 'Sayzio Showcase', ['#7c3aed', '#22d3ee', '#f472b6'], 'Confident, warm, playful'],
            ['sub-brand', 'Showcase Studio', ['#0ea5e9', '#f59e0b', '#111827'], 'Bold, modern, minimal'],
        ];
        $out = [];
        foreach ($defs as $i => [$suffix, $name, $palette, $tone]) {
            $link = $this->makeLink('brand_kit', "brandkit-{$suffix}", 'Brand Kit: ' . Str::headline($suffix));
            BrandKit::create([
                'user_id' => $this->user->id,
                'name' => $name,
                'slug' => static::HANDLE . '-brandkit-' . $suffix,
                'is_default' => $i === 0,
                'config' => [
                    'palette' => ['primary' => $palette[0], 'secondary' => $palette[1], 'accent' => $palette[2]],
                    'fonts' => ['heading' => 'Space Grotesk', 'body' => 'Inter'],
                    'voice' => ['tone' => $tone, 'descriptors' => ['friendly', 'confident', 'clear']],
                    'taglines' => ["Showcasing every Sayzio feature.", "Built to demo, ready to ship."],
                    'block_theme' => 'glass-dark',
                    'logo' => $this->img("brandkit-{$suffix}-logo", 240, 240),
                ],
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: ai_chat ───────────────────────────────────────────────

    /** @return Link[] */
    private function seedAiChatLinks(): array
    {
        $persona = AiPersonaAgent::create([
            'user_id' => $this->user->id,
            'name' => 'Sana\'s Showcase Assistant',
            'description' => 'Answers questions about the product and encourages sign-up.',
            'system_prompt' => 'You are a helpful assistant for the Sayzio showcase account. Answer questions about link-in-bio pages, QR codes, and analytics.',
            'tone_preset' => 'friendly',
            'model' => 'gpt-4o-mini',
            'greeting' => 'Hi! Ask me anything about this showcase.',
            'starter_questions' => ['What can Sayzio do?', 'Show me the pricing', 'How do QR codes work?'],
        ]);

        $defs = ['support-bot', 'sales-bot'];
        $out = [];
        foreach ($defs as $suffix) {
            $link = $this->makeLink('ai_chat', "aichat-{$suffix}", 'AI Chatbot: ' . Str::headline($suffix));
            $companion = AiCompanion::create([
                'user_id' => $this->user->id,
                'persona_id' => $persona->id,
                'public_id' => AiCompanion::newPublicId(),
                'name' => 'Showcase Assistant: ' . Str::headline($suffix),
                'placement' => 'page',
                'config' => [
                    'greeting' => 'Hi! Ask me anything about this showcase.',
                    'starters' => ['What can Sayzio do?', 'Show me the pricing', 'How do QR codes work?'],
                ],
                'free_turns_per_month' => -1,
            ]);
            DB::table('ai_companion_links')->insertOrIgnore([
                'companion_id' => $companion->id,
                'link_id' => $link->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $out[] = $link;
        }
        return $out;
    }

    // ── Link type: conversational ────────────────────────────────────────

    /** @return Link[] */
    private function seedConversationalLinks(): array
    {
        $defs = ['discover', 'onboarding'];
        $out = [];
        foreach ($defs as $suffix) {
            $link = $this->makeLink('conversational', "conv-{$suffix}", 'Conversational: ' . Str::headline($suffix));
            $flow = ConversationFlow::create([
                'link_id' => $link->id,
                'workspace_id' => $this->workspace->id,
                'name' => 'Showcase Flow: ' . Str::headline($suffix),
                'version' => 1,
                'is_published' => true,
                'is_active' => true,
                'intro_message' => 'Hi! Let\'s find what you\'re looking for.',
            ]);
            $steps = [
                ['key' => 'welcome', 'kind' => 'message', 'message_text' => 'Welcome to the showcase!'],
                ['key' => 'question', 'kind' => 'question', 'message_text' => 'What brings you here today?'],
                ['key' => 'done', 'kind' => 'message', 'message_text' => 'Thanks for chatting with us!'],
            ];
            $stepRows = [];
            foreach ($steps as $i => $step) {
                $stepRows[] = array_merge($step, [
                    'flow_id' => $flow->id,
                    'sort_order' => $i,
                    'is_entry' => $i === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('conversation_steps')->insert($stepRows);
            $out[] = $link;
        }
        return $out;
    }

    // ── Usage-explainer pages (one per link type) ────────────────────────

    /**
     * One "usage explainer" biolink page per supported link type: a short,
     * hand-written walkthrough of what the type is for and what you can do
     * with it, ending in a CTA that opens one of this account's own live
     * demo links of that type. Copy/block pattern mirrors
     * {@see LinkTypeExplainerSeeder} (heading → intro → checklist → CTA)
     * but the pages live on the showcase account and link to its demos.
     *
     * @return Link[]
     */
    private function seedExplainerLinks(): array
    {
        $out = [];
        foreach ($this->explainerPages() as $page) {
            $link = $this->makeLink('biolink', 'explain-' . $page['slug'], $page['title'], [
                'settings' => [
                    'biolink' => [
                        'biolink_title' => $page['title'],
                        'biolink_description' => $page['intro'],
                    ],
                ],
            ]);
            $this->buildExplainerBlocks($link, $page);
            $out[] = $link;
        }
        return $out;
    }

    /**
     * Heading / intro / checklist / CTA blocks with fully-populated
     * settings and styles (no `_placeholder` first-paint banner). The
     * public renderer echoes each list item directly as a string, so
     * `items` must stay a flat string[].
     */
    private function buildExplainerBlocks(Link $link, array $page): void
    {
        $defs = [
            ['heading', ['text' => $page['heading'], 'size' => 'h1', 'align' => 'center', 'style' => 'plain']],
            ['paragraph', ['text' => $page['intro'], 'align' => 'center']],
            ['heading', ['text' => 'What you can do', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['list', ['style' => 'checklist', 'icon' => 'fa-check', 'items' => array_values($page['features'])]],
            ['link', ['text' => $page['cta_label'], 'url' => url('/' . $page['cta_alias']), 'icon' => $page['cta_icon'] ?? 'fa-arrow-right']],
        ];

        foreach ($defs as $i => [$type, $settings]) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($type)
            );
            BiolinkBlock::forceCreate([
                'link_id' => $link->id,
                'type' => $type,
                'settings' => $settings,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Explainer copy for all 16 supported link types. Each entry: slug
     * (alias suffix), title, heading, intro, 4–5 feature bullets, and a CTA
     * pointing at one of this account's own live demo links of that type.
     */
    private function explainerPages(): array
    {
        $h = static::HANDLE;

        return [
            [
                'slug' => 'url', 'title' => 'Short Links: how to use them',
                'heading' => 'Short Links 🔗',
                'intro' => 'Turn long, ugly URLs into clean, branded links you can share anywhere, and see every click in real time.',
                'features' => [
                    'Shorten any URL into a clean, on-brand link',
                    'Repoint the destination any time, the link never changes',
                    'Add UTMs, password protection and expiry rules',
                    'Route visitors by country, device or language',
                    'Track every click with live analytics',
                ],
                'cta_label' => 'See a live short link', 'cta_alias' => "{$h}-url-product-launch", 'cta_icon' => 'fa-link',
            ],
            [
                'slug' => 'file', 'title' => 'File Links: how to use them',
                'heading' => 'File Links 📁',
                'intro' => 'Share PDFs, images and downloads from one tidy link with an optional branded download page.',
                'features' => [
                    'Upload once, share a single short link anywhere',
                    'Optional download page with your branding',
                    'Swap the file later without changing the link',
                    'See download counts in your analytics',
                ],
                'cta_label' => 'Open a sample file link', 'cta_alias' => "{$h}-file-media-kit", 'cta_icon' => 'fa-file-arrow-down',
            ],
            [
                'slug' => 'ics', 'title' => 'Event Links: how to use them',
                'heading' => 'Event Links 📅',
                'intro' => 'One link that adds your event straight to any calendar, no attachments, no confusion.',
                'features' => [
                    'Share date, time, location and organizer in one link',
                    'Visitors add it to Google, Apple or Outlook in one tap',
                    'Update details later, everyone gets the latest',
                    'Track interest through link clicks',
                ],
                'cta_label' => 'Try a live event link', 'cta_alias' => "{$h}-ics-webinar", 'cta_icon' => 'fa-calendar-plus',
            ],
            [
                'slug' => 'vcf', 'title' => 'Digital Cards: how to use them',
                'heading' => 'Digital Contact Cards 📇',
                'intro' => 'A modern business card: one link (or QR) that saves your full contact details to any phone.',
                'features' => [
                    'Full vCard: phones, emails, socials, address and more',
                    'Saves straight into the visitor\'s contacts app',
                    'Pair it with a QR code for events and print',
                    'Update your details without reprinting anything',
                ],
                'cta_label' => 'View a sample contact card', 'cta_alias' => "{$h}-vcf-personal", 'cta_icon' => 'fa-address-card',
            ],
            [
                'slug' => 'biolink', 'title' => 'Link in Bio: how to use it',
                'heading' => 'Link in Bio 🌟',
                'intro' => 'One beautiful page for all your links: built for the "link in bio" slot on every social platform.',
                'features' => [
                    'Drag-and-drop blocks for text, media, products and more',
                    'Dozens of themes and full visual customization',
                    'Capture emails, sell products and take bookings',
                    'Schedule blocks and hide them by device or location',
                    'Live analytics on every visit and click',
                ],
                'cta_label' => 'Explore a live bio page', 'cta_alias' => "{$h}-bio-personal", 'cta_icon' => 'fa-star',
            ],
            [
                'slug' => 'slides', 'title' => 'Slides: how to use them',
                'heading' => 'Slides 🖼️',
                'intro' => 'Tell your story as a swipeable, story-style presentation: perfect for portfolios and product tours.',
                'features' => [
                    'Swipeable, full-screen story slides',
                    'Mix text, images and links per slide',
                    'Great for portfolios, pitches and tutorials',
                    'Mobile-first, tap-to-advance experience',
                ],
                'cta_label' => 'Swipe through a live deck', 'cta_alias' => "{$h}-slides-story", 'cta_icon' => 'fa-images',
            ],
            [
                'slug' => 'restaurant-menu', 'title' => 'Restaurant Menu: how to use it',
                'heading' => 'Restaurant Menu 🍽️',
                'intro' => 'A digital menu with photos, prices and table-side ordering: a QR menu that actually takes orders.',
                'features' => [
                    'Build categories and items with photos and prices',
                    'Each table gets its own QR code and ordering link',
                    'Visitors order from the page; staff track it live',
                    'Coupons and tax build a live estimated bill',
                    'Update the menu any time, no reprinting',
                ],
                'cta_label' => 'Browse a live demo menu', 'cta_alias' => "{$h}-menu-downtown", 'cta_icon' => 'fa-utensils',
            ],
            [
                'slug' => 'store', 'title' => 'Store: how to use it',
                'heading' => 'Store 🛍️',
                'intro' => 'A simple product catalog with order requests: visitors browse, pick and send their order in seconds.',
                'features' => [
                    'Categories and products with photos and prices',
                    'Order requests with name and contact, no payment needed',
                    'Owner dashboard tracks New → Ready → Completed',
                    'Pause ordering any time with one toggle',
                    'Optional WhatsApp handoff for confirmations',
                ],
                'cta_label' => 'Visit a live demo store', 'cta_alias' => "{$h}-store-apparel", 'cta_icon' => 'fa-bag-shopping',
            ],
            [
                'slug' => 'booking', 'title' => 'Service Booking: how to use it',
                'heading' => 'Service Booking 🗓️',
                'intro' => 'Let clients book time with you straight from a link: pick a service, pick a slot, done.',
                'features' => [
                    'Offer services with set durations and availability',
                    'Visitors pick a slot without back-and-forth emails',
                    'Bookings land in your dashboard automatically',
                    'Share the link anywhere: bio page, DMs, email',
                ],
                'cta_label' => 'Try a live booking page', 'cta_alias' => "{$h}-booking-consult", 'cta_icon' => 'fa-calendar-check',
            ],
            [
                'slug' => 'resume', 'title' => 'Resume Links: how to use them',
                'heading' => 'Resume & Portfolio 📄',
                'intro' => 'A living resume you share as a link: always current, beautifully presented, with a PDF one tap away.',
                'features' => [
                    'Structured sections: experience, education, skills, projects',
                    'Share one link instead of emailing attachments',
                    'Keep named versions for different applications',
                    'Download as a polished PDF any time',
                    'See when people actually view it',
                ],
                'cta_label' => 'View a live resume', 'cta_alias' => "{$h}-resume-product-designer", 'cta_icon' => 'fa-file-lines',
            ],
            [
                'slug' => 'calendar', 'title' => 'Calendar Links: how to use them',
                'heading' => 'Followable Calendars 📆',
                'intro' => 'Publish a whole calendar of events people can follow: new events appear in their calendar automatically.',
                'features' => [
                    'One link for your full schedule of events',
                    'Visitors subscribe once, updates sync automatically',
                    'Works with Google, Apple and Outlook calendars',
                    'Perfect for classes, communities and office hours',
                ],
                'cta_label' => 'Follow a live calendar', 'cta_alias' => "{$h}-cal-events", 'cta_icon' => 'fa-calendar-days',
            ],
            [
                'slug' => 'paid-page', 'title' => 'Paid Pages: how to use them',
                'heading' => 'Paid Pages 💎',
                'intro' => 'Put your best content behind a link: supporters unlock it, you keep 100% of what you earn.',
                'features' => [
                    'Gate exclusive content behind a subscription or payment',
                    'Reuses your creator feed with visibility tiers',
                    'Pick a template that fits your content',
                    '0% platform fee on creator payouts',
                ],
                'cta_label' => 'Peek at a live paid page', 'cta_alias' => "{$h}-paid-creator-hub", 'cta_icon' => 'fa-gem',
            ],
            [
                'slug' => 'reviews', 'title' => 'Review Pages: how to use them',
                'heading' => 'Reviews ⭐',
                'intro' => 'Collect and showcase reviews on one page: native reviews plus imports from Google and Trustpilot.',
                'features' => [
                    'Visitors leave reviews right on your page',
                    'Import and merge Google and Trustpilot reviews',
                    'Moderate everything before it goes live',
                    'Pin your best reviews to the top',
                    'Embed a reviews wall on your bio page',
                ],
                'cta_label' => 'See a live reviews page', 'cta_alias' => "{$h}-reviews-product", 'cta_icon' => 'fa-star-half-stroke',
            ],
            [
                'slug' => 'brand-kit', 'title' => 'Brand Kits: how to use them',
                'heading' => 'Brand Kits 🎨',
                'intro' => 'One shareable page for your logos, colors, fonts and voice, so everyone stays on brand.',
                'features' => [
                    'Collect logos, palette, typography and tone in one link',
                    'Share with partners, press and collaborators',
                    'Keep separate kits for sub-brands or campaigns',
                    'AI features reuse your kit to stay on brand',
                ],
                'cta_label' => 'Open a live brand kit', 'cta_alias' => "{$h}-brandkit-main", 'cta_icon' => 'fa-palette',
            ],
            [
                'slug' => 'ai-chat', 'title' => 'AI Chatbot: how to use it',
                'heading' => 'AI Chatbot 🤖',
                'intro' => 'A smart AI companion that answers questions about you or your business 24/7, in your voice.',
                'features' => [
                    'An AI that answers around the clock',
                    'Trained on your bio, links and the details you choose',
                    'Handles FAQs so you do not have to',
                    'Lives on its own shareable chat page',
                    'Stays on-brand with full theming',
                ],
                'cta_label' => 'Chat with a live bot', 'cta_alias' => "{$h}-aichat-support-bot", 'cta_icon' => 'fa-robot',
            ],
            [
                'slug' => 'conversational', 'title' => 'Conversational Pages: how to use them',
                'heading' => 'Conversational 💬',
                'intro' => 'Guide visitors through your links one friendly message at a time: great for onboarding and storytelling.',
                'features' => [
                    'A chat-style flow that reveals links step by step',
                    'Write a fixed script that feels personal',
                    'Keep visitors focused on one action at a time',
                    'Perfect for launches, funnels and FAQs',
                ],
                'cta_label' => 'Walk through a live flow', 'cta_alias' => "{$h}-conv-discover", 'cta_icon' => 'fa-comments',
            ],
        ];
    }

    // ── Fresh main link-in-bio page ──────────────────────────────────────

    /**
     * The account's headline link-in-bio page at `/{handle}` — designed
     * from scratch for this showcase (hero, socials, featured links into
     * the live demos, newsletter capture). Deliberately NOT a copy of the
     * marketing /demo page.
     */
    private function seedMainBioPage(): Link
    {
        $h = static::HANDLE;

        $link = Link::create([
            'user_id' => $this->user->id,
            'type' => 'biolink',
            'alias' => $h,
            'title' => 'Sana Rahman: Everything in one link',
            'is_active' => true,
            'visibility' => 'public',
            'is_demo' => true,
            'settings' => [
                'biolink' => [
                    'biolink_title' => 'Sana Rahman',
                    'biolink_description' => 'Designer, founder & creator: everything I make, teach and sell, in one link.',
                ],
            ],
        ]);

        $defs = [
            ['avatar', ['url' => $this->img('sana-avatar', 320, 320), 'size' => 112, 'rounded' => true]],
            ['heading', ['text' => 'Sana Rahman ✨', 'size' => 'h1', 'align' => 'center', 'style' => 'plain']],
            ['paragraph', ['text' => 'Designer, founder & creator. I help small brands look big: browse my work, book time with me, or grab something from the studio store.', 'align' => 'center']],
            ['socials', ['platforms' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/sanarahman'],
                ['platform' => 'twitter', 'url' => 'https://twitter.com/sanarahman'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@sanarahman'],
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/sanarahman'],
            ]]],
            ['heading', ['text' => 'Work with me', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['link', ['text' => 'Book a strategy call', 'url' => url("/{$h}-booking-consult"), 'icon' => 'fa-calendar-check']],
            ['link', ['text' => 'My resume & portfolio', 'url' => url("/{$h}-resume-product-designer"), 'icon' => 'fa-file-lines']],
            ['link', ['text' => 'Chat with my AI assistant', 'url' => url("/{$h}-aichat-support-bot"), 'icon' => 'fa-robot']],
            ['heading', ['text' => 'From the studio', 'size' => 'h3', 'align' => 'center', 'style' => 'plain']],
            ['link', ['text' => 'Shop the apparel collection', 'url' => url("/{$h}-store-apparel"), 'icon' => 'fa-bag-shopping']],
            ['link', ['text' => 'Our story, in slides', 'url' => url("/{$h}-slides-story"), 'icon' => 'fa-images']],
            ['link', ['text' => 'What clients say', 'url' => url("/{$h}-reviews-product"), 'icon' => 'fa-star']],
            ['email_subscribe', [
                'title' => 'Get my monthly studio notes',
                'description' => 'One email a month: what I shipped, learned and loved.',
                'placeholder' => 'you@example.com',
                'button_text' => 'Subscribe',
                'success_message' => 'Thanks, you\'re on the list!',
                'name_field' => false,
            ]],
            ['paragraph', ['text' => 'Made with Sayzio: one link for everything.', 'align' => 'center']],
        ];

        foreach ($defs as $i => [$type, $settings]) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($type)
            );
            BiolinkBlock::forceCreate([
                'link_id' => $link->id,
                'type' => $type,
                'settings' => $settings,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        return $link;
    }

    // ── Other feature surfaces ───────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int, Form> */
    private function seedForms()
    {
        $defs = [
            ['contact', 'Contact Us', [
                ['type' => 'text', 'label' => 'Name', 'required' => true],
                ['type' => 'email', 'label' => 'Email', 'required' => true],
                ['type' => 'textarea', 'label' => 'Message', 'required' => true],
            ]],
            ['event-rsvp', 'Event RSVP', [
                ['type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['type' => 'select', 'label' => 'Attending?', 'options' => ['Yes', 'No'], 'required' => true],
            ]],
            ['feedback', 'Feedback Survey', [
                ['type' => 'rating', 'label' => 'How was your experience?', 'required' => true],
                ['type' => 'textarea', 'label' => 'Any comments?', 'required' => false],
            ]],
        ];
        $restDefs = $defs;
        $firstDef = array_shift($restDefs);
        [$firstSlug, $firstTitle, $firstFields] = $firstDef;
        $first = Form::forceCreate([
            'slug' => static::HANDLE . '-form-' . $firstSlug,
            'title' => $firstTitle,
            'description' => "Showcase form: {$firstTitle}.",
            'fields' => $firstFields,
            'design' => [],
            'settings' => [],
            'notifications' => ['email' => static::EMAIL],
            'is_active' => true,
            'is_multi_step' => false,
            'user_id' => $this->user->id,
        ]);

        $restRows = [];
        foreach ($restDefs as [$slug, $title, $fields]) {
            $restRows[] = [
                'slug' => static::HANDLE . '-form-' . $slug,
                'title' => $title,
                'description' => "Showcase form: {$title}.",
                'fields' => json_encode($fields),
                'design' => json_encode([]),
                'settings' => json_encode([]),
                'notifications' => json_encode(['email' => static::EMAIL]),
                'is_active' => true,
                'is_multi_step' => false,
                'user_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($restRows) {
            DB::table('forms')->insert($restRows);
        }

        $forms = Form::where('user_id', $this->user->id)
            ->whereIn('slug', array_map(fn ($d) => static::HANDLE . '-form-' . $d[0], $defs))
            ->get();
        $this->seedFormSubmissions($forms);

        return $first;
    }

    /** Sample visitor submissions for each showcase form, so the inbox isn't empty. */
    private function seedFormSubmissions($forms): void
    {
        $submitters = [
            ['Alex Carter', 'alex.carter@example.com'],
            ['Priya Nair', 'priya.nair@example.com'],
            ['Jordan Lee', 'jordan.lee@example.com'],
            ['Maria Gomez', 'maria.gomez@example.com'],
        ];

        $rows = [];
        foreach ($forms as $form) {
            $fieldLabels = collect($form->fields ?? [])->pluck('label')->all();
            $submissionCount = random_int(2, count($submitters));
            for ($i = 0; $i < $submissionCount; $i++) {
                [$name, $email] = $submitters[$i];
                $data = [];
                foreach ($fieldLabels as $label) {
                    $data[$label] = str_contains(strtolower($label), 'email')
                        ? $email
                        : (str_contains(strtolower($label), 'name') ? $name : 'Showcase sample response for "' . $label . '".');
                }
                $rows[] = [
                    'form_id' => $form->id,
                    'workspace_id' => $this->workspace->id,
                    'data' => json_encode($data),
                    'files' => json_encode([]),
                    'ip' => '203.0.113.' . random_int(1, 254),
                    'user_agent' => 'Mozilla/5.0 (Showcase Seed)',
                    'referrer' => 'https://sayzio.example.com',
                    'country' => 'US',
                    'is_spam' => false,
                    'is_read' => $i % 2 === 0,
                    'is_starred' => $i === 0,
                    'created_at' => now()->subDays(random_int(1, 45)),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows) {
            DB::table('form_submissions')->insert($rows);
        }
    }

    private function seedQrCodes(array $urlLinks): void
    {
        $defs = [
            ['url', ['url' => 'https://sayzio.example.com'], $urlLinks[0] ?? null],
            ['wifi', ['ssid' => 'Sayzio-Guest', 'password' => 'showcase123', 'encryption' => 'WPA'], null],
            ['vcard', ['first_name' => 'Sana', 'last_name' => 'Rahman', 'organization' => 'Sayzio Inc.', 'email' => static::EMAIL], null],
            ['email', ['email' => static::EMAIL, 'subject' => 'Hello from your QR code'], null],
            ['location', ['lat' => 37.7749, 'lng' => -122.4194, 'label' => 'Sayzio HQ'], null],
        ];
        foreach ($defs as $i => [$type, $payload, $link]) {
            QrCode::create([
                'user_id' => $this->user->id,
                'link_id' => $link?->id,
                'name' => 'Showcase QR: ' . Str::headline($type),
                'type' => $type,
                'payload' => $payload,
                'design' => ['fg_color' => '#111827', 'bg_color' => '#ffffff'],
            ]);
        }
    }

    private function seedSubscribers(?Link $link): void
    {
        $names = ['Alex Carter', 'Priya Nair', 'Jordan Lee', 'Maria Gomez', 'Tom Becker', 'Yuki Tanaka', 'Liam O\'Connor', 'Fatima Ali', 'Chen Wei', 'Nina Petrova'];
        $rows = [];
        foreach ($names as $i => $name) {
            $type = $i % 2 === 0 ? 'email' : 'whatsapp';
            $rows[] = [
                'user_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'link_id' => $link?->id,
                'type' => $type,
                'email' => $type === 'email' ? Str::slug($name) . '@example.com' : null,
                'phone' => $type === 'whatsapp' ? '+1555020' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT) : null,
                'name' => $name,
                'status' => 'confirmed',
                'source' => 'showcase-seed',
                'subscribed_at' => now()->subDays(random_int(1, 60)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('subscribers')->insert($rows);
    }

    private function seedReviewsAndProviders(?Link $link): void
    {
        ReviewProvider::create([
            'user_id' => $this->user->id,
            'provider' => 'google_places',
            'external_ref' => 'showcase-place-id',
            'status' => ReviewProvider::STATUS_PREVIEW,
            'status_reason' => 'No API key configured; preview mode.',
            'settings' => [],
        ]);
        ReviewProvider::create([
            'user_id' => $this->user->id,
            'provider' => 'trustpilot',
            'external_ref' => 'showcase-domain.example.com',
            'status' => ReviewProvider::STATUS_PREVIEW,
            'status_reason' => 'No API key configured; preview mode.',
            'settings' => [],
        ]);

        $reviewers = [
            ['Sarah K.', 5, 'Absolutely love using this. The setup took minutes.'],
            ['Marcus D.', 4, 'Great feature set, a couple of small UI quirks.'],
            ['Elena R.', 5, 'Best link-in-bio tool I have tried.'],
            ['James P.', 3, 'Solid product, would like more templates.'],
            ['Aisha M.', 5, 'The analytics dashboard is a huge time-saver.'],
            ['Tom H.', 4, 'Very customizable, exactly what our team needed.'],
            ['Ravi S.', 5, 'Support was fast and the product just works.'],
            ['Chloe B.', 4, 'Nice design system, easy to match our brand.'],
        ];
        $reviewRows = [];
        foreach ($reviewers as $i => [$name, $rating, $body]) {
            $reviewRows[] = [
                'user_id' => $this->user->id,
                'link_id' => $link?->id,
                'author_name' => $name,
                'author_email' => Str::slug($name) . '@example.com',
                'rating' => $rating,
                'body' => $body,
                'status' => Review::STATUS_APPROVED,
                'is_pinned' => $i === 0,
                'verified_at' => now()->subDays(random_int(1, 30)),
                'verification_method' => Review::METHOD_EMAIL,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('reviews')->insert($reviewRows);
    }

    private function seedBuzz(): SocialProof
    {
        $defs = [
            ['recent_activity', 'Recent Signups'],
            ['visitor_count', 'Live Visitor Count'],
            ['trust_badge', 'Trust Badge'],
            ['announcement_bar', 'Announcement Bar'],
        ];
        $restDefs = $defs;
        $firstDef = array_shift($restDefs);
        [$firstType, $firstName] = $firstDef;

        $first = SocialProof::create([
            'user_id' => $this->user->id,
            'uuid' => (string) Str::uuid(),
            'name' => $firstName,
            'type' => $firstType,
            'is_active' => true,
            'settings' => [],
            'design' => [],
            'targeting' => [],
            'schedule' => [],
            'notifications' => [],
            'impressions' => random_int(50, 500),
            'clicks' => random_int(5, 50),
            'conversions' => random_int(0, 10),
        ]);

        $restRows = [];
        foreach ($restDefs as [$type, $name]) {
            $restRows[] = [
                'user_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'type' => $type,
                'is_active' => true,
                'settings' => json_encode([]),
                'design' => json_encode([]),
                'targeting' => json_encode([]),
                'schedule' => json_encode([]),
                'notifications' => json_encode([]),
                'impressions' => random_int(50, 500),
                'clicks' => random_int(5, 50),
                'conversions' => random_int(0, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($restRows) {
            DB::table('social_proofs')->insert($restRows);
        }

        return $first;
    }

    private function seedContacts(): void
    {
        $names = ['Ava Thompson', 'Noah Kim', 'Isabella Rossi', 'Ethan Brooks', 'Mia Chen', 'Lucas Silva', 'Zara Malik', 'Oliver Wright', 'Layla Haddad', 'Benjamin Cole', 'Grace Park', 'Daniel Osei', 'Sofia Moretti', 'Henry Adeyemi', 'Amara Singh'];

        $contactRows = [];
        foreach ($names as $i => $name) {
            $contactRows[] = [
                'user_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'display_name' => $name,
                'given_name' => explode(' ', $name)[0],
                'family_name' => explode(' ', $name)[1] ?? '',
                'organization' => $i % 3 === 0 ? 'Sayzio Partner Co.' : null,
                'sources' => json_encode(['manual']),
                'tags' => json_encode($i % 2 === 0 ? ['showcase', 'lead'] : ['showcase']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('contacts')->insert($contactRows);

        $contactIds = Contact::where('user_id', $this->user->id)
            ->whereIn('display_name', $names)
            ->pluck('id', 'display_name');

        $phoneRows = [];
        $emailRows = [];
        foreach ($names as $i => $name) {
            $contactId = $contactIds[$name];
            $phoneRows[] = [
                'contact_id' => $contactId,
                'label' => 'mobile',
                'value' => '+1555030' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $emailRows[] = [
                'contact_id' => $contactId,
                'label' => 'personal',
                'value' => Str::slug($name) . '@example.com',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('contact_phones')->insert($phoneRows);
        DB::table('contact_emails')->insert($emailRows);
    }

    // ── Backdated analytics: clicks / sessions / heatmap / rollups ──────

    private function seedAnalytics(array $linkIds): void
    {
        if (!$linkIds) {
            return;
        }

        $this->seedAnalyticsRows($linkIds);
        $this->seedBlockViews($linkIds);
        $this->runAnalyticsRollupCommands();
    }

    /**
     * Generates and inserts the raw backdated link_clicks/page_sessions rows
     * for the given links. Split out of {@see seedAnalytics()} so the (fast)
     * raw-row generation can be exercised independently of the (slow, many
     * round-trips over 120 days) rollup commands in
     * {@see runAnalyticsRollupCommands()}.
     */
    private function seedAnalyticsRows(array $linkIds): void
    {
        // Idempotent: clear any analytics rows from a prior run of this
        // step before regenerating, since seedAnalyticsForShowcaseUser()
        // is designed to be invoked as its own standalone (re-runnable)
        // step, separately from the wipe-then-rebuild in run().
        DB::table('link_clicks')->whereIn('link_id', $linkIds)->delete();
        DB::table('page_sessions')->whereIn('link_id', $linkIds)->delete();
        DB::table('link_click_daily')->whereIn('link_id', $linkIds)->delete();
        DB::table('link_click_daily_dimensions')->whereIn('link_id', $linkIds)->delete();

        $countries = ['US', 'GB', 'IN', 'DE', 'BR', 'AU', 'CA', 'FR', 'JP', 'NG'];
        $coords = [
            'US' => [37.7749, -122.4194], 'GB' => [51.5074, -0.1278], 'IN' => [19.0760, 72.8777],
            'DE' => [52.5200, 13.4050], 'BR' => [-23.5505, -46.6333], 'AU' => [-33.8688, 151.2093],
            'CA' => [43.6532, -79.3832], 'FR' => [48.8566, 2.3522], 'JP' => [35.6762, 139.6503],
            'NG' => [6.5244, 3.3792],
        ];
        $devices = ['mobile', 'desktop', 'tablet'];
        $browsers = ['Chrome', 'Safari', 'Firefox', 'Edge'];
        $referrers = ['https://google.com', 'https://instagram.com', 'https://twitter.com', 'direct', 'https://linkedin.com'];

        $clickRows = [];
        $sessionRows = [];
        $now = now();

        foreach ($linkIds as $linkId) {
            $clicksForLink = random_int(30, 90);
            for ($i = 0; $i < $clicksForLink; $i++) {
                $daysAgo = random_int(0, 89);
                $country = $countries[array_rand($countries)];
                [$lat, $lng] = $coords[$country];
                $clickedAt = (clone $now)->subDays($daysAgo)->subMinutes(random_int(0, 1439));

                $clickRows[] = [
                    'event_id' => (string) Str::uuid(),
                    'link_id' => $linkId,
                    'ip_address' => long2ip(random_int(0, 4294967295)),
                    'country_code' => $country,
                    'city' => null,
                    'latitude' => $lat + (random_int(-50, 50) / 100),
                    'longitude' => $lng + (random_int(-50, 50) / 100),
                    'browser' => $browsers[array_rand($browsers)],
                    'os' => $devices[array_rand($devices)] === 'mobile' ? 'iOS' : 'Windows',
                    'device_type' => $devices[array_rand($devices)],
                    'referrer' => $referrers[array_rand($referrers)],
                    'language' => 'en',
                    'is_bot' => false,
                    'clicked_at' => $clickedAt,
                ];
            }

            $sessionsForLink = random_int(10, 30);
            for ($i = 0; $i < $sessionsForLink; $i++) {
                $daysAgo = random_int(0, 89);
                $country = $countries[array_rand($countries)];
                [$lat, $lng] = $coords[$country];
                $startedAt = (clone $now)->subDays($daysAgo)->subMinutes(random_int(0, 1439));
                $duration = random_int(10, 300);

                $sessionRows[] = [
                    'link_id' => $linkId,
                    'session_id' => (string) Str::uuid(),
                    'ip_address' => long2ip(random_int(0, 4294967295)),
                    'country_code' => $country,
                    'city' => null,
                    'latitude' => $lat + (random_int(-50, 50) / 100),
                    'longitude' => $lng + (random_int(-50, 50) / 100),
                    'browser' => $browsers[array_rand($browsers)],
                    'os' => 'Web',
                    'device_type' => $devices[array_rand($devices)],
                    'referrer' => $referrers[array_rand($referrers)],
                    'language' => 'en',
                    'started_at' => $startedAt,
                    'last_seen_at' => (clone $startedAt)->addSeconds($duration),
                    'duration_seconds' => $duration,
                    'ended' => true,
                    'created_at' => $startedAt,
                    'updated_at' => $startedAt,
                ];
            }
        }

        foreach (array_chunk($clickRows, 500) as $chunk) {
            DB::table('link_clicks')->insert($chunk);
        }
        foreach (array_chunk($sessionRows, 500) as $chunk) {
            DB::table('page_sessions')->insert($chunk);
        }
    }

    /**
     * Generates and inserts backdated `block_views` rows for every biolink
     * block belonging to the given links, so per-block engagement analytics
     * (impressions/view duration) aren't empty on the showcase account.
     * Idempotent: clears any prior rows for these links' blocks first.
     */
    private function seedBlockViews(array $linkIds): void
    {
        $blocks = DB::table('biolink_blocks')
            ->whereIn('link_id', $linkIds)
            ->select('id', 'link_id', 'type')
            ->get();

        if ($blocks->isEmpty()) {
            return;
        }

        $blockIds = $blocks->pluck('id')->all();
        DB::table('block_views')->whereIn('block_id', $blockIds)->delete();

        $now = now();
        $rows = [];

        foreach ($blocks as $block) {
            $viewsForBlock = random_int(5, 25);
            for ($i = 0; $i < $viewsForBlock; $i++) {
                // block_views has a unique (session_id, block_id) constraint,
                // so mint a fresh session id per row rather than reusing the
                // ones generated in seedAnalyticsRows().
                $sessionId = (string) Str::uuid();

                $daysAgo = random_int(0, 89);
                $firstViewedAt = (clone $now)->subDays($daysAgo)->subMinutes(random_int(0, 1439));
                $impressions = random_int(1, 4);
                $duration = random_int(500, 45000);

                $rows[] = [
                    'link_id' => $block->link_id,
                    'block_id' => $block->id,
                    'block_type' => $block->type,
                    'session_id' => $sessionId,
                    'view_duration_ms' => $duration,
                    'impression_count' => $impressions,
                    'first_viewed_at' => $firstViewedAt,
                    'last_viewed_at' => (clone $firstViewedAt)->addMilliseconds($duration),
                    'created_at' => $firstViewedAt,
                    'updated_at' => $firstViewedAt,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('block_views')->insert($chunk);
        }
    }

    /**
     * Runs the aggregate/rollup artisan commands that turn the raw
     * link_clicks/page_sessions rows generated above into the counters and
     * daily rollups the dashboards read. Split out of {@see seedAnalytics()}
     * so it can also be invoked on its own — this step is the slow part on a
     * distant RDS instance (120 days x several queries/day), independent of
     * how fast the raw-row generation above is.
     */
    private function runAnalyticsRollupCommands(): void
    {
        // Fail-closed: a non-zero exit (or thrown exception) from any of
        // these means the raw link_clicks/page_sessions/block_views rows
        // seeded above never got turned into the counters/rollups the
        // dashboards actually read, which would silently leave the
        // showcase account's analytics incomplete. Surface that as a hard
        // seeder failure instead of a swallowed warning.
        foreach ([
            ['analytics:recount-link-stats', []],
            ['analytics:flush-counters', []],
            ['analytics:rollup-daily', ['--days' => 120]],
        ] as [$command, $args]) {
            $exitCode = Artisan::call($command, $args);
            if ($exitCode !== 0) {
                throw new \RuntimeException(sprintf(
                    'ShowcaseAccountSeeder: `%s` exited with code %d: %s',
                    $command,
                    $exitCode,
                    trim(Artisan::output())
                ));
            }
        }
    }
}
