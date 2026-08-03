<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\Common\Services\CityLookupService;
use App\Modules\User\Controllers\LinkController as UserLinkController;
use App\Modules\User\Models\AbVariant;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BlockAnalyticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    use ApiResponses;

    /**
     * Validation closure constraining domain_id to a domain the caller
     * can actually attach: their own verified+active domains plus
     * admin-global active domains tagged for their plan (or untagged
     * globals open to every plan). Mirrors the web LinkController rule so
     * mobile-created links honour the same allow-list.
     */
    protected function availableDomainRule($user): \Closure
    {
        return function ($attribute, $value, $fail) use ($user) {
            if (empty($value)) return;
            $allowed = Domain::availableTo($user)->pluck('id')->all();
            if (!in_array((int) $value, $allowed, true)) {
                $fail('That domain is not available on your plan.');
            }
        };
    }

    /**
     * Live "Custom URL availability" check — mobile parity for the web
     * Create Link page's indicator. Shares AliasAvailability with
     * User\LinkController::checkAlias so the {status, available, message}
     * shape and the alias rules (alpha_dash, the caller's plan length
     * limits, the admin banned-names list and unique:links,alias) match
     * exactly what gets enforced when the link is actually created.
     *
     * Returns the plain shape (not the {data} envelope) so it mirrors the
     * web endpoint 1:1; a blank alias is the auto-generate case.
     */
    public function checkAlias(Request $request)
    {
        $alias = (string) $request->query('alias', (string) $request->input('alias', ''));

        // Optional "ignore link id" — the mobile edit screen passes the link
        // being edited so its own current alias isn't reported as taken.
        $ignoreId = $request->query('ignore_id', $request->input('ignore_id'));
        $ignoreId = ($ignoreId === null || $ignoreId === '') ? null : (int) $ignoreId;

        // Optional target domain — uniqueness is per-domain, so the verdict
        // depends on which domain the alias would be bound to.
        $domainId = $request->query('domain_id', $request->input('domain_id'));

        return response()->json(
            \App\Modules\User\Support\AliasAvailability::check($request->user(), $alias, $ignoreId, $domainId)
        );
    }

    /**
     * Clipboard quick-shorten — mobile parity for the web header bolt
     * button (POST /user/links/quick-shorten, Task #6285/#6286). Accepts a
     * raw "destination" (web URL, email address, phone number, or bare
     * domain), normalizes it via the shared
     * UserLinkController::normalizeQuickDestination() so server-side
     * classification can never drift from web, validates the optional
     * custom alias with the exact same rule stack as the full create flow,
     * enforces the plan's max_links cap (the web route's CheckPlanLimit:links
     * equivalent — the Sanctum path has no web middleware), and creates a
     * short url-type link. Returns {id, short_url, long_url, kind}.
     */
    public function quickShorten(Request $request)
    {
        $user = $request->user();

        $aliasLimits = $user->getAliasLengthLimits();
        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:20000'],
            // Optional branded host — same allow-list as the full create flow
            // (own verified + plan-entitled global domains), and the alias
            // uniqueness check is scoped to the chosen domain namespace.
            'domain_id'   => ['nullable', $this->availableDomainRule($user)],
            'alias'       => ['nullable', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'], new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\Admin\Rules\NotBannedName(), new \App\Modules\User\Rules\UniqueAliasCi(null, $request->input('domain_id'))],
            // Inaccessible/unknown ids fall back to the active workspace in
            // resolveWorkspaceId(); this rule only rejects malformed input.
            'workspace_id' => ['nullable', 'integer'],
        ]);

        // Plan link cap — mirrors CheckPlanLimit:links on the web route.
        $features = $user->plan?->features;
        if (is_array($features)) {
            $maxLinks = $features['max_links'] ?? 5;
            $count    = $user->links()->count();
            if ($maxLinks !== -1 && $count >= $maxLinks) {
                return $this->planGate(
                    "You've reached your plan's link limit ({$maxLinks}). Upgrade your plan for more links.",
                    'max_links',
                    $user,
                    402,
                    'plan_upgrade_required',
                    $count
                );
            }
        }

        $normalized = UserLinkController::normalizeQuickDestination($validated['destination']);

        $alias = trim((string) ($validated['alias'] ?? ''));
        if ($alias === '') {
            $alias = Link::generateAlias();
        }

        if ($normalized === null) {
            // Plain text (not a URL/email/phone) — mirror the web endpoint:
            // save it as a `text`-type link whose public page renders the
            // full text with a copy button.
            $longUrl = null;
            $kind = 'text';
            $link = new Link(UserLinkController::quickTextAttributes($validated['destination']) + [
                'alias'     => $alias,
                'domain_id' => !empty($validated['domain_id']) ? (int) $validated['domain_id'] : null,
                'user_id'   => $user->id,
            ]);
        } else {
            [$longUrl, $kind] = $normalized;

            $link = new Link([
                'type'      => 'url',
                'long_url'  => $longUrl,
                'alias'     => $alias,
                'domain_id' => !empty($validated['domain_id']) ? (int) $validated['domain_id'] : null,
                'user_id'   => $user->id,
                'title'    => match ($kind) {
                    'email' => 'Email ' . preg_replace('/^mailto:/', '', $longUrl),
                    'phone' => 'Call ' . preg_replace('/^tel:/', '', $longUrl),
                    default => null,
                },
            ]);
        }

        // Tag the active workspace (the Sanctum path never runs
        // SetActiveWorkspace, so without this the link lands with
        // workspace_id = null and is hidden from the web list). Honour a
        // caller-supplied workspace_id (browser extension workspace picker)
        // the same way store() does — resolveWorkspaceId() only accepts
        // workspaces the caller can actually access.
        $workspaceId = $this->resolveWorkspaceId($user, $request->input('workspace_id'));
        if ($workspaceId !== null && Schema::hasColumn('links', 'workspace_id')) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();

        return $this->created([
            'id'        => $link->id,
            'short_url' => $link->getShortUrl(),
            'long_url'  => $longUrl,
            'kind'      => $kind,
        ]);
    }

    /**
     * Build the workspace-scoped base query for the caller's link list,
     * mirroring the web "My Links" page exactly: links owned by the ACTIVE
     * workspace's owner AND tagged with that workspace id. Before this, the
     * API listed every link owned by the caller across all workspaces (and
     * never the owner's links when the caller is a team member), so the
     * mobile Links tab showed a different set than the web list.
     *
     * Falls back to the legacy caller-owned query when the workspace
     * column/tables are unavailable (old DBs).
     */
    protected function scopedLinksQuery(Request $request)
    {
        $user = $request->user();

        try {
            if (Schema::hasColumn('links', 'workspace_id')) {
                $ws = $this->activeWorkspace($user);
                if ($ws) {
                    return Link::withoutWorkspaceScope()
                        ->where('user_id', $ws->owner_user_id)
                        ->where('workspace_id', $ws->id);
                }
            }
        } catch (\Throwable) {
            // fall through to legacy scoping
        }

        return Link::where('user_id', $user->id);
    }

    public function index(Request $request)
    {
        $q = $this->scopedLinksQuery($request)->with(['domain', 'project']);

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        $page = $q->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        // Batch-preload pixel-fire data — without this every serialized link
        // fired 2 extra queries, making a 100-row page take minutes against
        // the distant RDS (the mobile "Couldn't load" timeouts).
        LinkResource::preload($page->items());

        return $this->ok([
            'items' => collect($page->items())->map(fn ($l) => LinkResource::toArray($l))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /**
     * CSV export of the caller's links, honouring the same `type`/`q`
     * filters as index(). Streamed + chunked so large accounts don't blow
     * memory. Mirrors the web /user/links/export (identical columns) and is
     * NOT plan-gated — exporting your own link list is data portability,
     * like the backlinks export (distinct from the plan-gated analytics
     * CSV exports).
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = $this->scopedLinksQuery($request)->with(['project', 'domain']);

        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        $filename = 'my-links-' . now()->format('Y-m-d') . '.csv';

        // Defend against CSV formula injection when opened in a spreadsheet —
        // prefix any cell starting with =, +, -, @ with a single quote so the
        // spreadsheet treats it as text.
        $safe = function ($value) {
            $s = (string) $value;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
                return "'" . $s;
            }
            return $s;
        };

        return response()->streamDownload(function () use ($q, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'title', 'type', 'short_url', 'destination',
                'project', 'status', 'total_clicks', 'created_at',
            ]);

            $q->orderByDesc('id')->chunk(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $link) {
                    fputcsv($out, [
                        $safe($link->title ?: $link->alias),
                        $safe($link->type),
                        $safe($link->getShortUrl()),
                        $safe($link->long_url),
                        $safe(optional($link->project)->name),
                        $link->is_active ? 'active' : 'inactive',
                        (int) $link->total_clicks,
                        optional($link->created_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function store(Request $request)
    {
        // Per-plan alias minimum (free/unconfigured = largest, paid tiers
        // step down). Enforced on the REST create path so mobile/API clients
        // land on the same floor as the web form and live checker.
        $aliasLimits = $request->user()->getAliasLengthLimits();
        $data = $request->validate([
            'type'       => ['required', Rule::in(['short', 'biolink', 'file', 'qr', 'event', 'ics', 'vcard', 'social', 'sms', 'wifi', 'pdf', 'conversational', 'slides', 'ai_chat', 'resume', 'paid_page', 'brand_kit', 'text', 'restaurant_menu', 'store_menu', 'service_booking', 'calendar', 'reviews', 'updates'])],
            // The admin banned/reserved-names list is enforced on the mobile
            // create submit too (privileged `user.banned_names.bypass` holders
            // skip it), mirroring the web chooseType() rule and the live
            // check-alias indicator so a reserved handle can't slip in via the
            // REST create path.
            'alias'      => ['nullable', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'], new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\Admin\Rules\NotBannedName(), new \App\Modules\User\Rules\UniqueAliasCi(null, $request->input('domain_id'))],
            'title'      => ['nullable', 'string', 'max:200'],
            'long_url'   => ['nullable', 'url', 'max:2048'],
            'visibility' => ['nullable', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['nullable', 'boolean'],
            'seo_title'  => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
            'settings'   => ['nullable', 'array'],
            // Custom/global domain to host this short link on. Optional —
            // when omitted the link uses the platform default host. The
            // mobile create flow pre-selects the admin-chosen primary
            // global domain, matching the web form.
            'domain_id'  => ['nullable', $this->availableDomainRule($request->user())],
            // Workspace tagging from the browser extension's workspace
            // selector. Optional — older clients (mobile) and
            // single-workspace accounts simply omit it. Persisted on the
            // links row only when the column exists, otherwise stashed
            // under settings.workspace_id so the choice isn't lost.
            'workspace_id' => ['nullable', 'integer'],
            // Auto-fire workspace tracking pixels (Meta / TikTok / Google
            // Ads) when this link is clicked. Optional — when omitted,
            // defaults to true if the workspace has any pixel configured,
            // false otherwise (so links created on workspaces with no
            // pixels stay direct 302s with zero perf cost).
            'auto_pixel'   => ['nullable', 'boolean'],
        ]);

        // The mobile "Event invite" create flow posts type "event" with a
        // loose settings.event blob. The canonical event link type is "ics"
        // with a companion IcsData row that the public event page + RSVP flow
        // (RedirectController) render from — a bare "event" link renders
        // nothing. Map it to "ics" here and build the IcsData row below so a
        // mobile-created event resolves to the same page as a web-created one
        // (Task #3680). We also accept "ics" directly so duplicating an event
        // (which re-posts the stored type) keeps working. The web
        // IcsLinkController create path is unchanged.
        $isEvent = in_array($data['type'], ['event', 'ics'], true);
        if ($isEvent) {
            $data['type'] = 'ics';
            // A fresh event create (type "event") must carry a name + start;
            // a duplicate (type "ics") simply reuses the copied settings.event
            // blob and falls back to sensible defaults below.
            if ($request->input('type') === 'event') {
                $request->validate([
                    'title'                => ['required', 'string', 'max:200'],
                    'settings.event.start' => ['required', 'date'],
                    'settings.event.end'   => ['nullable', 'date'],
                ]);
            }
        }

        // Text Page links carry their pasted body under settings.text.content
        // (same storage shape as the web create form and quick-shorten sheet),
        // so require it here and cap it at the shared 20k-char limit.
        if ($data['type'] === 'text') {
            $request->validate([
                'settings.text.content' => ['required', 'string', 'max:20000'],
            ]);
        }

        // File links need a companion FileLink row for the public download
        // route (RedirectController::handleFileDownload 404s without one).
        // The desktop browser flow uploads to the Sayzio Files vault first
        // (POST /me/files/upload) then passes the vault file id under
        // settings.file.id. Resolve + ownership-check the vault file BEFORE
        // creating the link so a bad reference never leaves a dangling,
        // unservable file link (Task #6247).
        $fileForLink = null;
        if ($data['type'] === 'file') {
            $request->validate([
                'settings.file.id' => ['required', 'integer'],
            ]);
            $fileForLink = $request->user()->files()
                ->find((int) $request->input('settings.file.id'));
            if (!$fileForLink) {
                return $this->fail('That file was not found in your Sayzio Files.', 422, 'file_not_found');
            }
        }

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $settingsPayload = $data['settings'] ?? [];
        // Fall back to the user's active workspace when the caller (e.g. the
        // mobile app) doesn't pass one, so the link isn't created with
        // workspace_id = null and hidden from the workspace-scoped web list.
        $workspaceId     = $this->resolveWorkspaceId($request->user(), $data['workspace_id'] ?? null);

        $attrs = [
            'user_id'    => $request->user()->id,
            'type'       => $data['type'],
            'alias'      => $alias,
            'title'      => $data['title'] ?? null,
            'long_url'   => $data['long_url'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'is_active'  => $data['is_active'] ?? true,
            'seo_title'  => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'domain_id'  => $data['domain_id'] ?? null,
        ];

        // workspace_id is not mass-assignable; when the column doesn't exist
        // we stash it under settings, otherwise it's set on the model below.
        $hasWorkspaceColumn = Schema::hasColumn('links', 'workspace_id');
        if ($workspaceId !== null && !$hasWorkspaceColumn) {
            $settingsPayload['workspace_id'] = (int) $workspaceId;
        }

        // Auto-pixel default: if the caller didn't specify, derive from
        // the target workspace's pixel configuration. Workspaces with at
        // least one pixel ID configured opt every new link in by default;
        // empty workspaces stay opted out so the redirect remains a
        // direct 302 with no interstitial cost.
        if (Schema::hasColumn('links', 'auto_pixel')) {
            if (array_key_exists('auto_pixel', $data)) {
                $attrs['auto_pixel'] = (bool) $data['auto_pixel'];
            } else {
                $attrs['auto_pixel'] = $this->workspaceHasPixels($workspaceId);
            }
        }

        $attrs['settings'] = $settingsPayload;

        // Per-link-type plan caps + on/off module toggles for page types that
        // the mobile client can create. Mirrors the web enforceLinkTypeQuota()
        // so mobile callers hit the same plan limits as the web form. Absent
        // plan keys default to "enabled / unlimited" for forward-compatibility.
        $typeQuotaMap = [
            'conversational' => ['module' => 'module_conversational', 'cap' => 'max_conversational', 'label' => 'Conversational'],
            'slides'         => ['module' => 'module_slides',         'cap' => 'max_slides',         'label' => 'Slides'],
            'ai_chat'        => ['module' => 'module_ai_chat',        'cap' => 'max_ai_chat',        'label' => 'AI Chatbot'],
            'resume'         => ['module' => 'module_resume',         'cap' => 'max_resume',         'label' => 'Resume / Portfolio'],
            'paid_page'      => ['module' => 'module_paid_page',      'cap' => 'max_paid_page',      'label' => 'Paid Page'],
            'brand_kit'      => ['module' => 'module_brand_kit',      'cap' => 'max_brand_kit_pages','label' => 'Brand / Press Kit'],
            'text'           => ['module' => 'module_text',           'cap' => 'max_text_pages',     'label' => 'Text Page'],
            // Editor-backed page types creatable from the desktop browser's
            // "+ Create" popover. Module/cap keys mirror the web
            // enforceLinkTypeQuota() map exactly so both surfaces gate alike.
            'restaurant_menu' => ['module' => 'module_restaurant_menu', 'cap' => 'max_restaurant_menu', 'label' => 'Restaurant Menu'],
            'store_menu'      => ['module' => 'module_store_menu',      'cap' => 'max_store_menu',      'label' => 'Store Menu'],
            'service_booking' => ['module' => 'module_service_booking', 'cap' => 'max_service_booking', 'label' => 'Service Booking'],
            'calendar'        => ['module' => 'module_calendar',        'cap' => 'max_calendars',       'label' => 'Calendar'],
            'reviews'         => ['module' => 'module_reviews',         'cap' => 'max_reviews',         'label' => 'Reviews'],
            'updates'         => ['module' => 'module_updates',         'cap' => 'max_updates_pages',   'label' => 'Updates'],
        ];
        if (isset($typeQuotaMap[$attrs['type']])) {
            $qcfg  = $typeQuotaMap[$attrs['type']];
            $owner = $request->user();
            if (!$owner->getPlanFeature($qcfg['module'], true)) {
                return $this->planGate(
                    "{$qcfg['label']} pages aren't available on your current plan. Upgrade to enable them.",
                    $qcfg['module'], $owner
                );
            }
            $count = $owner->links()->where('type', $attrs['type'])->count();
            if (!$owner->planUnderLimit($qcfg['cap'], $count, -1)) {
                $max = (int) $owner->getPlanFeature($qcfg['cap'], -1);
                return $this->planGate(
                    "You've reached your plan's {$qcfg['label']} page limit ({$max}). Upgrade your plan for more.",
                    $qcfg['cap'], $owner, 402, 'plan_upgrade_required', $count
                );
            }
        }

        $link = new Link($attrs);
        if ($workspaceId !== null && $hasWorkspaceColumn) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();

        // Companion IcsData row for event links so the public event page +
        // RSVP flow render from the canonical source (`ics_data`) rather than
        // the loose settings.event blob — matching a web-created event
        // (Task #3680).
        if ($isEvent) {
            $this->createEventData($link, (array) ($settingsPayload['event'] ?? []));
        }

        // Resume / Portfolio links bridge to the user's standalone resume
        // builder record. Associate the owner's default resume so the public
        // page and PDF export resolve through the existing renderer — mirrors
        // the web LinkController::store() behavior.
        if ($link->type === 'resume') {
            $resume = $request->user()->ensureResume();
            $link->resume_id = $resume->id;
            $link->save();
        }

        // Paid Page links seed a default design template into settings so the
        // public render always resolves a theme even before the owner opens
        // the editor — mirrors the web LinkController::store() behavior.
        if ($link->type === 'paid_page') {
            $settings = (array) ($link->settings ?? []);
            $paidPage = (array) ($settings['paid_page'] ?? []);
            $requested = $paidPage['template'] ?? null;
            $paidPage['template'] = \App\Modules\User\Support\PaidPageTemplates::exists($requested)
                ? $requested
                : \App\Modules\User\Support\PaidPageTemplates::DEFAULT_ID;
            $settings['paid_page'] = $paidPage;
            $link->settings = $settings;
            $link->save();
        }

        // Brand / Press Kit links seed their per-link config from the owner's
        // saved AI Brand Kit (palette / fonts / voice / taglines / bio) so the
        // public page is presentable the moment it is created — mirrors the web
        // LinkController::store() behavior. Pages default to public.
        if ($link->type === 'brand_kit') {
            $kit = \App\Modules\User\Models\BrandKit::where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();
            $settings = (array) ($link->settings ?? []);
            $settings['brand_kit'] = \App\Modules\User\Support\BrandKitPageTemplates::prefillFromKit($kit, $request->user());
            $link->settings = $settings;
            if (($link->visibility ?? null) === null) {
                $link->visibility = 'public';
            }
            $link->save();
        }

        // Restaurant Menu / Store Menu / Service Booking links seed their
        // companion builder rows with the same defaults the web editors use
        // on first open (RestaurantMenuController::menuFor /
        // StoreMenuController::menuFor / ServiceBookingController::bookingFor),
        // so the public page renders correctly the moment it is created.
        if ($link->type === 'restaurant_menu') {
            \App\Modules\User\Models\RestaurantMenu::firstOrCreate(
                ['link_id' => $link->id],
                ['user_id' => $link->user_id, 'mode' => \App\Modules\User\Models\RestaurantMenu::MODE_DISPLAY, 'currency' => 'USD']
            );
        }
        if ($link->type === 'store_menu') {
            \App\Modules\User\Models\StoreMenu::firstOrCreate(
                ['link_id' => $link->id],
                ['user_id' => $link->user_id, 'mode' => \App\Modules\User\Models\StoreMenu::MODE_DISPLAY, 'currency' => 'USD']
            );
        }
        if ($link->type === 'service_booking') {
            \App\Modules\User\Models\ServiceBooking::firstOrCreate(
                ['link_id' => $link->id],
                [
                    'user_id'             => $link->user_id,
                    'mode'                => \App\Modules\User\Models\ServiceBooking::MODE_BOOKING,
                    'currency'            => 'USD',
                    'slot_length_minutes' => 30,
                    'lead_time_minutes'   => 120,
                    'max_days_ahead'      => 30,
                    'timezone'            => \App\Support\PlatformTimezone::forUser($request->user()),
                ]
            );
        }

        // Calendar links bridge 1:1 to a followable Calendar collection —
        // mirrors the web LinkController::store() seeding (title/slug/tz/
        // accent, public by default) so the page is followable immediately.
        if ($link->type === 'calendar') {
            $calSettings = (array) ($settingsPayload['calendar'] ?? []);
            $tz = (string) ($calSettings['timezone'] ?? '');
            if ($tz === '' || !in_array($tz, timezone_identifiers_list(), true)) {
                $tz = \App\Support\PlatformTimezone::forUser($request->user());
            }
            $accent = (string) ($calSettings['accent_color'] ?? '#3d6bff');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                $accent = '#3d6bff';
            }
            $calendar = \App\Modules\User\Models\Calendar::create([
                'link_id'      => $link->id,
                'user_id'      => $link->user_id,
                // Task #6619 — stamp the calendar into the link's workspace.
                'workspace_id' => $link->workspace_id,
                'title'        => $link->title ?: 'My Calendar',
                'slug'         => $link->alias,
                'description'  => (string) ($calSettings['description'] ?? ''),
                'timezone'     => $tz,
                'accent_color' => $accent,
                'is_public'    => true,
            ]);
            $link->calendar_id = $calendar->id;
            $link->visibility = 'public';
            $link->save();
        }

        // Reviews / Updates pages store their configuration in link settings.
        // Seed the same defaults the web editors start from so the public
        // page is presentable before the owner ever opens the editor.
        if ($link->type === 'reviews') {
            $settings = (array) ($link->settings ?? []);
            $settings['reviews'] = array_replace(
                \App\Modules\User\Controllers\ReviewsController::DEFAULT_SETTINGS,
                (array) ($settings['reviews'] ?? [])
            );
            $link->settings = $settings;
            $link->save();
        }
        if ($link->type === 'updates') {
            $settings = (array) ($link->settings ?? []);
            $settings['updates'] = array_replace(
                \App\Modules\User\Controllers\UpdatesController::DEFAULT_SETTINGS,
                (array) ($settings['updates'] ?? [])
            );
            $link->settings = $settings;
            $link->save();
        }

        // Companion FileLink row for file links so the public short URL
        // actually serves the vault file (mirrors the web
        // FileLinkController::store and WhatsAppAgentTools::createFileLink).
        if ($fileForLink !== null) {
            \App\Modules\User\Models\FileLink::create([
                'link_id'       => $link->id,
                'original_name' => $fileForLink->original_name,
                'stored_path'   => $fileForLink->path,
                'mime_type'     => $fileForLink->mime_type,
                'file_size'     => $fileForLink->size_bytes,
                'disk'          => $fileForLink->disk,
            ]);
            if (!$link->title) {
                $link->title = $fileForLink->original_name;
                $link->save();
            }
        }

        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    public function show(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->with('domain')->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok(['link' => LinkResource::toArray($link)]);
    }

    public function update(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $aliasLimits = $request->user()->getAliasLengthLimits();
        $data = $request->validate([
            'title'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'long_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
            // The admin banned/reserved-names list is enforced on the mobile
            // edit submit too (privileged `user.banned_names.bypass` holders
            // skip it), mirroring the create path so a reserved handle can't
            // slip in by renaming an existing link via the REST update path.
            'alias'      => ['sometimes', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'], new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\Admin\Rules\NotBannedName(), new \App\Modules\User\Rules\UniqueAliasCi($link->id, $request->has('domain_id') ? $request->input('domain_id') : $link->domain_id)],
            'visibility' => ['sometimes', Rule::in(['public', 'registered', 'followers', 'subscribers'])],
            'is_active'  => ['sometimes', 'boolean'],
            'seo_title'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'settings'   => ['sometimes', 'nullable', 'array'],
            'auto_pixel' => ['sometimes', 'boolean'],
            'domain_id'  => ['sometimes', 'nullable', $this->availableDomainRule($request->user())],
        ]);

        // Text Page body parity with create + web edit: the PATCH path must
        // enforce the same shared 20k-char cap on settings.text.content, or a
        // third-party API client could store an arbitrarily large body on an
        // existing text link that the create/web forms would have rejected.
        if ($link->type === 'text' && $request->has('settings.text.content')) {
            $request->validate([
                'settings.text.content' => ['nullable', 'string', 'max:20000'],
            ]);
        }

        if (array_key_exists('settings', $data)) {
            // Deep-merge supplied keys into the existing settings JSON so
            // mobile clients can patch a single sub-key (e.g. just
            // `appearance.theme`) without clobbering the rest.
            $existing = (array) ($link->settings ?? []);
            $patch    = (array) ($data['settings'] ?? []);

            // Design lock parity with the web page-settings save: while a
            // page is design-locked, every styling key under settings.biolink
            // (plus block_theme/layout and the lock stamp itself) is
            // server-owned — strip them from the patch so content-level
            // settings still merge but styling can't drift via the API.
            if ($link->isDesignLocked()) {
                if (is_array($patch['biolink'] ?? null)) {
                    $lockedKeys = array_merge(
                        \App\Modules\User\Controllers\BiolinkBlockController::DESIGN_LOCKED_PAGE_KEYS,
                        ['block_theme', 'layout', 'background_image', 'torn_image', 'slideshow_images', 'video_file', 'bg_fallback_image', 'favicon_url', 'design_locked']
                    );
                    foreach ($lockedKeys as $k) {
                        unset($patch['biolink'][$k]);
                    }
                } elseif (array_key_exists('biolink', $patch) && !is_array($patch['biolink'])) {
                    unset($patch['biolink']);
                }
            } elseif (is_array($patch['biolink'] ?? null)) {
                // The lock stamp is only ever written server-side (template
                // apply / detach) — never accept it from the client.
                unset($patch['biolink']['design_locked']);
            }

            $data['settings'] = array_replace_recursive($existing, $patch);

            // Page stickers: array_replace_recursive merges numeric-key lists
            // element-wise (a shorter patched list would keep stale trailing
            // items), so when the patch carries biolink.stickers we REPLACE
            // the whole sanitized list after the merge. Design-locked pages
            // never reach here for this key (stripped above).
            if (is_array($patch['biolink'] ?? null) && array_key_exists('stickers', $patch['biolink'])) {
                $data['settings']['biolink']['stickers'] =
                    \App\Modules\User\Support\BiolinkStickers::sanitize($patch['biolink']['stickers']);
            }
        }

        $link->fill($data)->save();
        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    /**
     * Reset all click counters & analytics rows for a link the caller
     * owns. Used by the mobile "Reset" action under link settings.
     */
    public function reset(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        DB::transaction(function () use ($link) {
            $link->forceFill([
                'total_clicks'  => 0,
                'unique_clicks' => 0,
            ])->save();

            if (Schema::hasTable('click_events')) {
                DB::table('click_events')->where('link_id', $link->id)->delete();
            }
            if (Schema::hasTable('link_clicks')) {
                DB::table('link_clicks')->where('link_id', $link->id)->delete();
            }

            // Analytics now reads finalized days from the daily rollups (see
            // AnalyticsRollupReader), so a reset must clear that link's rollup
            // rows too — otherwise by_day/by_country/by_device/by_source would
            // keep returning pre-reset values from the surviving rollup rows.
            if (Schema::hasTable('link_click_daily_dimensions')) {
                DB::table('link_click_daily_dimensions')->where('link_id', $link->id)->delete();
            }
            if (Schema::hasTable('link_click_daily')) {
                DB::table('link_click_daily')->where('link_id', $link->id)->delete();
            }
        });

        return $this->ok(['link' => LinkResource::toArray($link->fresh())]);
    }

    /**
     * Read or update the per-biolink rate-limit override that
     * {@see \App\Modules\Common\Services\VisitorRateLimiter} consults
     * on every visit. PATCH-merges into `link.settings.rate_limit` so
     * partial updates (just toggling `enabled`, just bumping the IP
     * cap) don't clobber the other keys.
     */
    public function rateLimit(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        if ($request->isMethod('GET')) {
            return $this->ok([
                'rate_limit' => app(\App\Modules\Common\Services\VisitorRateLimiter::class)
                    ->configFor($link),
            ]);
        }

        $data = $request->validate([
            'enabled'    => ['sometimes', 'boolean'],
            'ip_per_min' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'fp_per_min' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        $settings = (array) ($link->settings ?? []);
        $current  = (array) ($settings['rate_limit'] ?? []);
        foreach ($data as $k => $v) {
            $current[$k] = $v;
        }
        $settings['rate_limit'] = $current;
        $link->settings = $settings;
        $link->save();

        return $this->ok([
            'rate_limit' => app(\App\Modules\Common\Services\VisitorRateLimiter::class)
                ->configFor($link->fresh()),
        ]);
    }

    /**
     * Build the companion IcsData row for an event created via the REST
     * create path (the mobile "Event invite"). Mirrors the canonical shape
     * the web IcsLinkController persists so the public event page + RSVP flow
     * (RedirectController) render identically. The mobile client posts
     * ISO-8601 UTC timestamps in settings.event; `end` defaults to one hour
     * after `start` when omitted or invalid (the web form requires an
     * explicit end, but mobile keeps it optional), `timezone` defaults to UTC,
     * and `event_name` falls back to the link title. All three of
     * `event_name`/`start_date`/`end_date` are NOT NULL, so every branch here
     * resolves a concrete value.
     */
    protected function createEventData(Link $link, array $event): void
    {
        $tz = (is_string($event['timezone'] ?? null) && $event['timezone'] !== '')
            ? $event['timezone']
            : 'UTC';

        try {
            $startDt = new \DateTime((string) ($event['start'] ?? 'now'));
        } catch (\Throwable $e) {
            $startDt = new \DateTime();
        }

        $endDt = null;
        if (!empty($event['end'])) {
            try {
                $endDt = new \DateTime((string) $event['end']);
            } catch (\Throwable $e) {
                $endDt = null;
            }
        }
        if (!$endDt || $endDt < $startDt) {
            $endDt = (clone $startDt)->modify('+1 hour');
        }

        \App\Modules\User\Models\IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title ?: 'Event',
            'location'   => (is_string($event['location'] ?? null) && $event['location'] !== '')
                ? $event['location'] : null,
            'start_date' => $startDt->format('Y-m-d H:i:s'),
            'end_date'   => $endDt->format('Y-m-d H:i:s'),
            'timezone'   => $tz,
        ]);
    }

    /** True when the given workspace has any tracking pixel ID configured. */
    protected function workspaceHasPixels(?int $workspaceId): bool
    {
        if (!$workspaceId) return false;
        $ws = \App\Modules\User\Models\Workspace::query()->find($workspaceId);
        if (!$ws) return false;
        $p = (array) (data_get($ws->settings, 'pixels', []) ?? []);
        return !empty($p['meta_id']) || !empty($p['tiktok_id']) || !empty($p['google_id']);
    }

    public function destroy(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $link->delete();
        return $this->noContent();
    }

    /**
     * Per-link analytics summary for the mobile dashboard. Aggregates
     * the click_events table over an optional [from, to] window and
     * groups by day, country, referrer, and device. Falls back to the
     * Link model's denormalised counters when the click_events table is
     * unavailable (older installs / read-replica latency) so the mobile
     * client always gets a usable response.
     */
    public function analytics(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        $payload = [
            'link_id'       => $link->id,
            'alias'         => $link->alias,
            'total_clicks'  => (int) ($link->total_clicks ?? 0),
            'unique_clicks' => (int) ($link->unique_clicks ?? 0),
            'window'        => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'by_day'      => [],
            'by_country'  => [],
            'by_referrer' => [],
            'by_device'   => [],
            'by_source'   => [],
        ];

        // by_day / by_country / by_referrer / by_device are served by the
        // AnalyticsRollupReader: pre-aggregated link_click_daily rows for
        // finalised days unioned with the small raw tail (today + late clicks).
        // This bounds the scanned row count for long windows while matching a
        // full raw aggregate exactly. (Historically these read a phantom
        // `click_events` table that was never populated, so they returned empty.)
        $reader = app(\App\Modules\Common\Services\AnalyticsRollupReader::class);

        $payload['by_day'] = $reader->byDay($link->id, $from, $to);
        $payload['by_country'] = array_map(
            fn ($r) => ['country' => $r['value'], 'clicks' => $r['clicks']],
            $reader->byDimension($link->id, $from, $to, 'country', 50)
        );
        $payload['by_referrer'] = array_map(
            fn ($r) => ['referrer_host' => $r['value'], 'clicks' => $r['clicks']],
            $reader->byDimension($link->id, $from, $to, 'referrer', 50)
        );
        $payload['by_device'] = array_map(
            fn ($r) => ['device_type' => $r['value'], 'clicks' => $r['clicks']],
            $reader->byDimension($link->id, $from, $to, 'device', 50)
        );

        // Per-block click summary — list of every block that received clicks
        // in the window with title/type so the mobile app can render a
        // tap-through list on the analytics screen.
        $payload['by_block'] = BlockAnalyticsAggregator::blockSummary($link, $from, $to);

        // Audience Insights: self-identified visitor persona breakdown from page_sessions.
        // Only present for non-empty datasets (visitor_type column populated after migration).
        $audienceRows = \App\Modules\User\Models\PageSession::where('link_id', $link->id)
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('visitor_type')
            ->selectRaw('visitor_type, count(*) as count')
            ->groupBy('visitor_type')
            ->orderByDesc('count')
            ->get();
        $audienceTotal = max(1, $audienceRows->sum('count'));
        $payload['by_visitor_type'] = $audienceRows->map(fn ($r) => [
            'type'    => $r->visitor_type,
            'count'   => (int) $r->count,
            'pct'     => round(($r->count / $audienceTotal) * 100, 1),
        ])->values()->all();

        // Cached AI-estimated audience mix (produced by the owner-triggered
        // web estimate endpoint) so mobile can render the same combined
        // self-identified + estimated Audience Type breakdown.
        $audienceEstimate = $link->settings['biolink']['audience_estimate'] ?? null;
        $payload['audience_estimate'] = is_array($audienceEstimate) ? $audienceEstimate : null;

        // Coin-cost hint for the mobile "Get AI Estimate" button (parity
        // with the web "Uses up to N coins per run" note). 0 when the
        // caller's plan doesn't include the feature or estimation fails.
        $estimateCoins = 0;
        if (\App\Services\AI\AiPlanAccess::featureAllowed($request->user(), \App\Services\AI\AudienceTypeEstimationService::FEATURE_KEY)) {
            try {
                $estimateCoins = (int) (app(\App\Services\AI\AiCostEstimator::class)
                    ->estimate($request->user(), \App\Services\AI\AudienceTypeEstimationService::FEATURE_KEY, '')['coins'] ?? 0);
            } catch (\Throwable $e) {
                $estimateCoins = 0;
            }
        }
        $payload['audience_estimate_coins'] = $estimateCoins;

        // Caller's coin-wallet balance so the mobile app can warn (and
        // disable the estimate button) BEFORE a run that the wallet can't
        // cover, instead of a dead-end tap + generic failure.
        $payload['coin_balance'] = app(\App\Services\Billing\WalletService::class)->getBalance($request->user());

        // A/B variant breakdown — populated when the link was created via
        // the browser extension's "Shorten as A/B test" flow. The popup
        // (and dashboard) renders the per-variant counts and surfaces the
        // current leader.
        $payload['ab_variants'] = $this->abVariantsPayload($link);

        // Mobile-app vs web split — pulled directly from `link_clicks` since
        // that's where the LinkTrackingService records the source tag. Works
        // independently of the optional `click_events` rollup table.
        // Exclude bot + throttled rows so the source split mirrors the
        // human-only totals shown elsewhere on the dashboard. Schema may
        // pre-date `is_throttled` on older installs — guard the column.
        $bySourceQuery = \DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->whereBetween('clicked_at', [$from, $to])
            ->where('is_bot', false);
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            $bySourceQuery->where('is_throttled', false);
        }
        $payload['by_source'] = $bySourceQuery
            ->selectRaw("COALESCE(source, 'unknown') as source, count(*) as clicks")
            ->groupBy('source')
            ->orderByDesc('clicks')
            ->get()
            ->all();

        // Text-page download / raw-fetch counts — parity with the web link
        // analytics page. `serveTextContent()` records these interactions as
        // link_clicks rows tagged source `txt_download` (the Download .txt
        // button) and `txt_raw` (the /raw plain-text endpoint). Uses the
        // LinkClick model relation so the default "no bots" global scope
        // applies — same exclusions as the web numbers.
        $payload['link_type'] = $link->type;
        $payload['txt_downloads'] = 0;
        $payload['txt_raw'] = 0;
        if ($link->type === 'text') {
            $txtCounts = $link->clicks()
                ->whereBetween('clicked_at', [$from, $to])
                ->whereIn('source', ['txt_download', 'txt_raw'])
                ->selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source');
            $payload['txt_downloads'] = (int) ($txtCounts['txt_download'] ?? 0);
            $payload['txt_raw'] = (int) ($txtCounts['txt_raw'] ?? 0);
        }

        // Bot + throttled traffic the global LinkClick scope hides from
        // the human-only stats above. We surface it here so the mobile
        // dashboard can render the "Blocked X bot attempts this week"
        // badge alongside a small per-day series matching the chart
        // window. Schema may pre-date `is_throttled` on older installs;
        // fall back to bot-only counting in that case.
        $blockedQuery = \DB::table('link_clicks')
            ->where('link_id', $link->id);
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            $blockedQuery->where(function ($w) {
                $w->where('is_bot', true)->orWhere('is_throttled', true);
            });
        } else {
            $blockedQuery->where('is_bot', true);
        }

        $weekFrom = now()->subDays(7);
        $payload['blocked_total']      = (int) (clone $blockedQuery)
            ->whereBetween('clicked_at', [$from, $to])->count();
        $payload['blocked_this_week']  = (int) (clone $blockedQuery)
            ->where('clicked_at', '>=', $weekFrom)->count();
        $payload['blocked_by_day']     = (clone $blockedQuery)
            ->whereBetween('clicked_at', [$from, $to])
            ->selectRaw("to_char(clicked_at, 'YYYY-MM-DD') as day, count(*) as clicks")
            ->groupBy('day')->orderBy('day')->get()->all();
        $payload['rate_limit']         = app(\App\Modules\Common\Services\VisitorRateLimiter::class)
            ->configFor($link);

        // Per-rule breakdown for smart links — counts clicks attributed
        // to each rule id stamped by RedirectController. Joined with the
        // link's persisted rule list so the response can include each
        // rule's type/label, not just a raw id.
        if (Schema::hasColumn('link_clicks', 'matched_rule_id')) {
            $rows = DB::table('link_clicks')
                ->where('link_id', $link->id)
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('matched_rule_id')
                ->selectRaw('matched_rule_id, count(*) as clicks')
                ->groupBy('matched_rule_id')
                ->orderByDesc('clicks')
                ->get();

            $rulesByLabel = collect((array) ($link->settings['smart_rules'] ?? []))
                ->keyBy(fn ($r) => $r['id'] ?? '');
            $payload['by_rule'] = $rows->map(function ($r) use ($rulesByLabel) {
                $rule = $rulesByLabel->get($r->matched_rule_id);
                return [
                    'rule_id' => $r->matched_rule_id,
                    'type'    => $rule['type'] ?? null,
                    'label'   => $rule['label'] ?? null,
                    'clicks'  => (int) $r->clicks,
                ];
            })->values()->all();
        } else {
            $payload['by_rule'] = [];
        }

        return $this->ok(['analytics' => $payload]);
    }

    /**
     * Per-block analytics drill-down. Returns clicks-per-day, top
     * referrers, device split and the visitor-type breakdown
     * (anonymous / registered / follower / subscriber) for one block on
     * the link. Powers the drill-down panel on the mobile analytics
     * screen and the equivalent modal on the web biolink analytics page.
     */
    public function blockAnalytics(Request $request, int $id, int $blockId)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        return $this->ok([
            'analytics' => BlockAnalyticsAggregator::aggregate($link, $blockId, $from, $to),
        ]);
    }

    /**
     * Per-link geographic click heatmap (mobile + API parity with the web
     * /links/{link}/heatmap surface). Aggregates `link_clicks` by exact
     * (lat, lng) over an optional [from, to] window into GeoJSON features +
     * a flat `points` array the mobile map can render directly. Older clicks
     * with a known city/country but no stored coordinates are resolved to a
     * coarse pin via the offline CityLookupService so they still appear.
     *
     * Bot/throttled rows are excluded automatically by the LinkClick global
     * scope applied to `$link->clicks()`. Scoped to the authenticated owner,
     * mirroring the sibling analytics endpoints (the Sanctum path never binds
     * a workspace context for the `workspace.can:stats.view` middleware, so
     * ownership is enforced directly here instead).
     */
    public function heatmap(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        // True period total (never truncated by the points cap below).
        $totalGeoClicks = (int) $link->clicks()
            ->whereBetween('clicked_at', [$from, $to])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        // Aggregate by exact (lat, lng) so each point is a real marker.
        // Capped at the busiest 5000 hotspots to bound response size.
        $rows = $link->clicks()
            ->whereBetween('clicked_at', [$from, $to])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('latitude', 'longitude')
            ->selectRaw('count(*) as click_count')
            ->selectRaw('max(city) as city')
            ->selectRaw('max(country_code) as country_code')
            ->groupBy('latitude', 'longitude')
            ->orderByDesc('click_count')
            ->limit(5000)
            ->get();

        $features = [];
        $maxWeight = 0;
        $shownClicks = 0;
        foreach ($rows as $r) {
            $count = (int) $r->click_count;
            $shownClicks += $count;
            if ($count > $maxWeight) $maxWeight = $count;
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $r->longitude, (float) $r->latitude],
                ],
                'properties' => [
                    'lat'          => (float) $r->latitude,
                    'lng'          => (float) $r->longitude,
                    'count'        => $count,
                    'weight'       => $count,
                    'city'         => $r->city,
                    'country_code' => $r->country_code,
                    'country'      => $r->country_code,
                ],
            ];
        }

        // Second pass: rows with a known city + country_code but no stored
        // lat/lng. Group by (city, country_code) and resolve each group to a
        // coarser pin via the offline CityLookupService so they still show up.
        $cityRows = $link->clicks()
            ->whereBetween('clicked_at', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->whereNotNull('city')
            ->whereNotNull('country_code')
            ->selectRaw('city, country_code, count(*) as click_count')
            ->groupBy('city', 'country_code')
            ->get();

        if ($cityRows->isNotEmpty()) {
            $lookup = app(CityLookupService::class);
            $resolvedTotal = 0;
            foreach ($cityRows as $r) {
                $coords = $lookup->lookup($r->city, $r->country_code);
                if (!$coords) continue;
                $count = (int) $r->click_count;
                $resolvedTotal += $count;
                $shownClicks += $count;
                if ($count > $maxWeight) $maxWeight = $count;
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $coords['longitude'], (float) $coords['latitude']],
                    ],
                    'properties' => [
                        'lat'          => (float) $coords['latitude'],
                        'lng'          => (float) $coords['longitude'],
                        'count'        => $count,
                        'weight'       => $count,
                        'city'         => $r->city,
                        'country_code' => $r->country_code,
                        'country'      => $r->country_code,
                        'approximate'  => true,
                    ],
                ];
            }
            $totalGeoClicks += $resolvedTotal;
            usort($features, fn($a, $b) => $b['properties']['count'] <=> $a['properties']['count']);
        }

        $points = array_map(fn($f) => [
            'lat'          => $f['properties']['lat'],
            'lng'          => $f['properties']['lng'],
            'count'        => $f['properties']['count'],
            'city'         => $f['properties']['city'],
            'country_code' => $f['properties']['country_code'],
            'approximate'  => $f['properties']['approximate'] ?? false,
        ], $features);

        return $this->ok([
            'heatmap' => [
                'type'     => 'FeatureCollection',
                'features' => $features,
                'points'   => $points,
                'meta'     => [
                    'max_weight'   => $maxWeight,
                    'point_count'  => count($features),
                    'total_clicks' => $totalGeoClicks,
                    'shown_clicks' => $shownClicks,
                    'period_start' => $from->toIso8601String(),
                    'period_end'   => $to->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Pollable "live" heatmap feed — the mobile-friendly counterpart to the
     * web SSE stream (/links/{link}/heatmap/live/stream). The client polls
     * this every few seconds passing the `last_id` it last saw; we return
     * only rows newer than that cursor so pins never duplicate across polls.
     *
     *  - First poll (no cursor): seeds the last 5 minutes of clicks so the
     *    "X live visitors" pill is accurate the moment live mode opens —
     *    matching the web `heatmapLive()` polling endpoint.
     *  - Subsequent polls (`lastId` set): returns every click with id greater
     *    than the cursor, regardless of the 5-minute window, so nothing is
     *    missed between polls (mirrors the web stream's tail loop).
     */
    public function heatmapLive(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $lastId  = (int) $request->query('lastId', 0);
        $sinceTs = $request->query('since');

        $cols = ['id', 'latitude', 'longitude', 'city', 'country_code', 'channel', 'clicked_at', 'ip_address'];

        if ($lastId > 0) {
            // Incremental tail: everything newer than the cursor.
            $rows = $link->clicks()
                ->where('id', '>', $lastId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->limit(200)
                ->get($cols);
        } else {
            // Initial seed window: last 5 minutes (or an explicit `since` ts).
            $windowStart = $sinceTs
                ? \Carbon\Carbon::createFromTimestamp((int) $sinceTs)
                : now()->subMinutes(5);
            $rows = $link->clicks()
                ->where('clicked_at', '>=', $windowStart)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->limit(200)
                ->get($cols);
        }

        $points = $this->formatLivePoints($rows);

        $maxId = $lastId;
        foreach ($rows as $r) {
            if ((int) $r->id > $maxId) $maxId = (int) $r->id;
        }

        // Unique humans seen in the rolling 5-minute window, independent of
        // the incremental cursor, so the live pill stays stable between polls.
        $uniqueVisitors = $link->clicks()
            ->where('clicked_at', '>=', now()->subMinutes(5))
            ->whereNotNull('ip_address')
            ->distinct('ip_address')
            ->count('ip_address');

        return $this->ok([
            'live' => [
                'points' => $points,
                'meta'   => [
                    'count'           => count($points),
                    'unique_visitors' => $uniqueVisitors,
                    'window_seconds'  => 300,
                    'server_time'     => now()->toIso8601String(),
                    'server_ts'       => now()->getTimestamp(),
                    'last_id'         => $maxId,
                ],
            ],
        ]);
    }

    /**
     * Shared point formatter for the live heatmap feed — mirrors the web
     * LinkController::formatLivePoints() so mobile receives the same shape
     * the browser live stream emits.
     */
    private function formatLivePoints($rows): array
    {
        $points = [];
        foreach ($rows as $r) {
            $channelKey = $r->channel ?: null;
            $points[] = [
                'id'            => (int) $r->id,
                'lat'           => (float) $r->latitude,
                'lng'           => (float) $r->longitude,
                'city'          => $r->city,
                'country_code'  => $r->country_code,
                'channel'       => $channelKey,
                'channel_label' => $channelKey
                    ? \App\Modules\Common\Services\ChannelClassifier::labelFor($channelKey)
                    : null,
                'clicked_at'    => optional($r->clicked_at)->toIso8601String(),
                'ts'            => optional($r->clicked_at)->getTimestamp(),
            ];
        }
        return $points;
    }

    /**
     * Create a new short link with smart-routing rules attached. Mirrors
     * the regular store() flow but enforces the `link_smart_rules` plan
     * gate and runs the supplied rule list through the shared sanitizer.
     * Used by the browser extension's "Shorten as Smart Link" button.
     */
    public function storeSmart(Request $request)
    {
        $user = $request->user();
        if (!$user->planFeatureEnabled('link_smart_rules')) {
            return $this->planGate('Smart links are not available on your current plan.', 'link_smart_rules', $user);
        }

        $aliasLimits = $user->getAliasLengthLimits();
        $data = $request->validate([
            'long_url'     => ['required', 'url', 'max:2048'],
            'title'        => ['nullable', 'string', 'max:200'],
            'alias'        => ['nullable', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'], new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\User\Rules\UniqueAliasCi()],
            'workspace_id' => ['nullable', 'integer'],
            'rules'        => ['required', 'array', 'min:1'],
        ]);

        $rules = UserLinkController::sanitizeSmartRules(json_encode($data['rules']));
        if (empty($rules)) {
            return $this->fail('No valid smart rules supplied.', 422, 'invalid_rules');
        }
        $maxRules = $this->resolveMaxRules($user);
        if (count($rules) > $maxRules) {
            return $this->planGate("Your plan allows up to {$maxRules} rules per smart link.", 'max_smart_rules', $user, 422, 'rule_limit_exceeded', count($rules));
        }

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        // Fall back to the user's active workspace when the caller doesn't
        // pass one, so mobile-created links aren't hidden from the web list.
        $workspaceId = $data['workspace_id'] ?? $this->activeWorkspaceId($user);
        $settings = ['smart_rules' => $rules];
        if (!empty($workspaceId)) {
            if (Schema::hasColumn('links', 'workspace_id')) {
                // attached below via $attrs
            } else {
                $settings['workspace_id'] = (int) $workspaceId;
            }
        }

        $attrs = [
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => $alias,
            'title'     => $data['title'] ?? null,
            'long_url'  => $data['long_url'],
            'is_active' => true,
            'settings'  => $settings,
        ];
        // workspace_id is not mass-assignable; set it directly after build.
        $link = new Link($attrs);
        if (!empty($workspaceId) && Schema::hasColumn('links', 'workspace_id')) {
            $link->workspace_id = (int) $workspaceId;
        }
        $link->save();
        return $this->created(['link' => LinkResource::toArray($link)]);
    }

    /**
     * Read the smart-routing rules attached to a link the caller owns.
     * Returns an empty list when the link has none — used by the
     * extension's inline rule editor.
     */
    public function getRules(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        $rules = (array) ($link->settings['smart_rules'] ?? []);
        return $this->ok([
            'link_id' => $link->id,
            'rules'   => array_values($rules),
            'max'     => $this->resolveMaxRules($request->user()),
        ]);
    }

    /**
     * Replace the smart-routing rules on an existing link. Unsets the
     * key entirely when the supplied list sanitizes down to nothing so
     * the link reverts to its plain destination URL.
     */
    public function putRules(Request $request, int $id)
    {
        $user = $request->user();
        $link = Link::where('user_id', $user->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        if (!$user->planFeatureEnabled('link_smart_rules')) {
            return $this->planGate('Smart rules are not available on your current plan.', 'link_smart_rules', $user);
        }

        $data = $request->validate([
            'rules' => ['present', 'array'],
        ]);
        $rules = UserLinkController::sanitizeSmartRules(json_encode($data['rules']));
        $maxRules = $this->resolveMaxRules($user);
        if (count($rules) > $maxRules) {
            return $this->planGate("Your plan allows up to {$maxRules} rules per smart link.", 'max_smart_rules', $user, 422, 'rule_limit_exceeded', count($rules));
        }

        $settings = (array) ($link->settings ?? []);
        if (empty($rules)) {
            unset($settings['smart_rules']);
        } else {
            $settings['smart_rules'] = $rules;
        }
        $link->settings = $settings;
        $link->save();

        return $this->ok([
            'link_id' => $link->id,
            'rules'   => $rules,
            'max'     => $maxRules,
        ]);
    }

    private function resolveMaxRules($user): int
    {
        $val = $user->getPlanFeature('max_smart_rules', null);
        if ($val === null) return 25;
        $n = (int) $val;
        if ($n < 0) return 25;
        return min(25, max(1, $n));
    }

    // ── A/B testing (browser-extension feature) ─────────────────────────
    //
    // Stored as an `ab_variants` row per destination, plus a flag and
    // (later) a winner pointer under `links.settings.ab_test`. The
    // redirect path performs sticky weighted assignment per visitor and
    // increments the per-variant counters.

    /**
     * Create a new short link AND its A/B variants in one shot, mirroring
     * the popup's single submission. Validates per-plan max-variants and
     * weight-sum-100 invariants.
     */
    public function storeAb(Request $request)
    {
        $aliasLimits = $request->user()->getAliasLengthLimits();
        $data = $request->validate([
            'title'        => ['nullable', 'string', 'max:200'],
            'alias'        => ['nullable', 'string', 'min:' . $aliasLimits['min'], 'max:' . $aliasLimits['max'], new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\User\Rules\UniqueAliasCi()],
            'workspace_id' => ['nullable', 'integer'],
            'variants'     => ['required', 'array', 'min:2'],
            'variants.*.label' => ['nullable', 'string', 'max:120'],
            'variants.*.url'   => ['required', 'url', 'max:2048'],
            'variants.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        if (!$this->planAllowsAbTests($user)) {
            return $this->forbidden('A/B testing is not available on your current plan.');
        }
        $maxVariants = $this->planMaxAbVariants($user);
        if (count($data['variants']) > $maxVariants) {
            return $this->forbidden("Your plan allows at most {$maxVariants} variants per A/B test.");
        }

        $sum = array_sum(array_map(fn ($v) => (int) $v['weight'], $data['variants']));
        if ($sum !== 100) {
            return $this->fail('Variant weights must sum to 100.', 422, 'invalid_weights');
        }

        $alias = $data['alias'] ?? Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $settings = ['ab_test' => [
            'enabled'           => true,
            'created_at'        => now()->toIso8601String(),
            'winner_variant_id' => null,
        ]];
        // Fall back to the user's active workspace when the caller doesn't
        // pass one, so mobile-created links aren't hidden from the web list.
        $workspaceId = $data['workspace_id'] ?? $this->activeWorkspaceId($user);
        if ($workspaceId) {
            if (Schema::hasColumn('links', 'workspace_id')) {
                $linkExtra = ['workspace_id' => (int) $workspaceId];
            } else {
                $settings['workspace_id'] = (int) $workspaceId;
                $linkExtra = [];
            }
        } else {
            $linkExtra = [];
        }

        // Use the first variant's URL as the link's "default" long_url
        // so any non-AB code path (link expired, AB cleared, etc.) still
        // has a valid destination.
        $firstUrl = $data['variants'][0]['url'];

        $link = DB::transaction(function () use ($user, $alias, $data, $settings, $linkExtra, $firstUrl) {
            // workspace_id is not mass-assignable; set it directly after build.
            $link = new Link([
                'user_id'    => $user->id,
                'type'       => 'url',
                'alias'      => $alias,
                'title'      => $data['title'] ?? null,
                'long_url'   => $firstUrl,
                'visibility' => 'public',
                'is_active'  => true,
                'settings'   => $settings,
            ]);
            if (!empty($linkExtra['workspace_id'])) {
                $link->workspace_id = (int) $linkExtra['workspace_id'];
            }
            $link->save();

            foreach ($data['variants'] as $i => $v) {
                AbVariant::create([
                    'link_id'    => $link->id,
                    'label'      => $v['label'] ?? null,
                    'url'        => $v['url'],
                    'weight'     => (int) $v['weight'],
                    'sort_order' => $i,
                ]);
            }
            return $link;
        });

        return $this->created([
            'link'     => LinkResource::toArray($link->fresh()),
            'variants' => $this->abVariantsPayload($link),
        ]);
    }

    /**
     * Inspect a single A/B test — variants, per-variant counts, leader.
     * Used by the popup's recent-tests widget every time it's opened.
     */
    public function showAb(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');
        return $this->ok([
            'link'     => LinkResource::toArray($link),
            'variants' => $this->abVariantsPayload($link),
        ]);
    }

    /**
     * List all the user's A/B tests. Powers the popup's "Recent A/B
     * tests" section. Capped server-side at the most recent 10 to keep
     * the popup snappy.
     */
    public function indexAb(Request $request)
    {
        $links = Link::where('user_id', $request->user()->id)
            ->whereHas('abVariants')
            ->with(['abVariants'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->ok([
            'items' => $links->map(fn ($l) => [
                'link'     => LinkResource::toArray($l),
                'variants' => $this->abVariantsPayload($l),
            ])->all(),
        ]);
    }

    /**
     * End the test by re-pointing the short link permanently to the
     * chosen variant. Variants stay in the table (so the dashboard can
     * still render the historical results), but the redirect path will
     * stop bucketing and just send everyone to `long_url`.
     */
    public function declareAbWinner(Request $request, int $id)
    {
        $link = Link::where('user_id', $request->user()->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
        ]);

        $winner = AbVariant::where('link_id', $link->id)->find($data['variant_id']);
        if (!$winner) return $this->notFound('Variant not found');

        DB::transaction(function () use ($link, $winner) {
            AbVariant::where('link_id', $link->id)->update(['is_winner' => false]);
            $winner->forceFill(['is_winner' => true])->save();

            $settings = (array) ($link->settings ?? []);
            $settings['ab_test'] = array_merge((array) ($settings['ab_test'] ?? []), [
                'winner_variant_id' => $winner->id,
                'winner_url'        => $winner->url,
                'declared_at'       => now()->toIso8601String(),
            ]);
            $link->forceFill([
                'settings' => $settings,
                'long_url' => $winner->url,
            ])->save();
        });

        return $this->ok([
            'link'     => LinkResource::toArray($link->fresh()),
            'variants' => $this->abVariantsPayload($link->fresh()),
        ]);
    }

    /**
     * Run the AI Audience Type Estimation for a link the caller owns —
     * mobile parity for the web POST /user/links/{link}/audience/estimate.
     * Same paid-plan gate, same 30-day window, and the result is cached
     * into link.settings['biolink']['audience_estimate'] so both the web
     * analytics panel and the mobile analytics payload pick it up.
     */
    public function estimateAudience(Request $request, int $id)
    {
        $user = $request->user();
        $link = Link::where('user_id', $user->id)->find($id);
        if (!$link) return $this->notFound('Link not found');

        if (!\App\Services\AI\AiPlanAccess::featureAllowed($user, \App\Services\AI\AudienceTypeEstimationService::FEATURE_KEY)) {
            return $this->planGate('AI Audience Estimation is available on paid plans.', \App\Services\AI\AudienceTypeEstimationService::FEATURE_KEY, $user);
        }

        // Cooldown: if the cached estimate is only minutes old, return it
        // without charging — protects coin wallets from accidental double runs.
        // An explicit `force: true` (behind a confirm dialog client-side)
        // intentionally bypasses the cooldown and pays for a fresh run.
        $cached = $link->settings['biolink']['audience_estimate'] ?? null;
        if (!$request->boolean('force') && \App\Services\AI\AudienceTypeEstimationService::estimateIsFresh($cached)) {
            return $this->ok([
                'estimated'     => $cached['data'],
                'credits_spent' => 0,
                'cached'        => true,
                'generated_at'  => $cached['generated_at'],
            ]);
        }

        try {
            $result = app(\App\Services\AI\AudienceTypeEstimationService::class)
                ->estimate($user, $link, now()->subDays(30), now());
        } catch (\App\Services\AI\InsufficientCoinsForAiException $e) {
            return $this->fail(
                'Not enough coins to run this estimate. Top up your wallet and try again.',
                402,
                'insufficient_credits',
                ['required' => $e->required, 'balance' => $e->balance]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audience estimation failed (API)', ['link_id' => $link->id, 'error' => $e->getMessage()]);
            return $this->fail('Estimation failed. Please try again.', 500, 'estimation_failed');
        }

        $settings = is_array($link->settings) ? $link->settings : [];
        $settings['biolink']['audience_estimate'] = [
            'data'          => $result['estimated'],
            'generated_at'  => now()->toIso8601String(),
            'credits_spent' => $result['credits_spent'],
        ];
        $link->update(['settings' => $settings]);

        return $this->ok([
            'estimated'     => $result['estimated'],
            'credits_spent' => $result['credits_spent'],
        ]);
    }

    /**
     * Per-variant counts + the current leader (by clicks). Returns an
     * empty array for non-AB links so callers can use a single shape.
     *
     * @return array{enabled:bool,winner_variant_id:?int,leader_variant_id:?int,variants:array}
     */
    protected function abVariantsPayload(Link $link): array
    {
        $variants = $link->abVariants()->get();
        if ($variants->isEmpty()) {
            return ['enabled' => false, 'winner_variant_id' => null, 'leader_variant_id' => null, 'variants' => []];
        }

        $items = $variants->map(fn ($v) => [
            'id'        => $v->id,
            'label'     => $v->label,
            'url'       => $v->url,
            'weight'    => (int) $v->weight,
            'visitors'  => (int) $v->visitors,
            'clicks'    => (int) $v->clicks,
            'is_winner' => (bool) $v->is_winner,
        ])->all();

        $leader = collect($items)->sortByDesc('clicks')->first();

        return [
            'enabled'            => true,
            'winner_variant_id'  => (int) (data_get($link->settings, 'ab_test.winner_variant_id') ?: 0) ?: null,
            'leader_variant_id'  => $leader ? (int) $leader['id'] : null,
            'variants'           => $items,
        ];
    }

    protected function planAllowsAbTests($user): bool
    {
        // Bypass holders skip ALL plan gating (same contract as
        // User::getPlanFeature / CheckPlanLimit).
        if ($user->hasPermission('user.plan_limits.bypass')) {
            return true;
        }
        $features = $user->plan?->features ?? [];
        // Default: paid plans get it. Free plan keeps it off via explicit
        // `ab_tests => false`. Older installs without the flag fall back
        // to `link_smart_rules` so existing capability is honored.
        if (array_key_exists('ab_tests', $features)) {
            return (bool) $features['ab_tests'];
        }
        return (bool) ($features['link_smart_rules'] ?? false);
    }

    protected function planMaxAbVariants($user): int
    {
        $features = $user->plan?->features ?? [];
        $max = $features['ab_max_variants'] ?? 4;
        $max = (int) $max;
        return $max > 0 ? min($max, 4) : 4;
    }
}
