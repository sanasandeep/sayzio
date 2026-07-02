<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\CityLookupService;
use App\Modules\Common\Services\AppLinkResolver;
use App\Modules\User\Concerns\RespondsWithUploadErrors;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\BlockAnalyticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class LinkController extends Controller
{
    use RespondsWithUploadErrors;

    /**
     * Build a Validation rule that constrains domain_id to a domain the
     * user can actually attach: their own verified+active domains plus
     * admin-global active domains tagged for their plan (or untagged
     * globals open to every plan). Replaces the old "user-owned only"
     * exists rule that broke admin-global domain selection.
     */
    protected function availableDomainRule(User $user): \Closure
    {
        return function ($attribute, $value, $fail) use ($user) {
            if (empty($value)) return;
            $allowed = \App\Modules\User\Models\Domain::availableTo($user)
                ->pluck('id')->all();
            if (!in_array((int) $value, $allowed, true)) {
                $fail('That domain is not available on your plan.');
            }
        };
    }

    public function index(Request $request)
    {
        $query = workspace_owner()->links()->with(['project', 'domain', 'fileLink']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('alias', 'ilike', "%{$search}%")
                  ->orWhere('long_url', 'ilike', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $links = $query->latest()->paginate(15)->withQueryString();
        $projects = workspace_owner()->projects()->orderBy('name')->get();

        // Lightweight, unfiltered roll-up for the bento command-center hero /
        // metric tiles so the header reflects the whole account, not the
        // currently filtered page.
        $owner = workspace_owner();
        $summary = [
            'total'   => (int) $owner->links()->count(),
            'active'  => (int) $owner->links()->where('is_active', true)->count(),
            'clicks'  => (int) $owner->links()->sum('total_clicks'),
        ];

        return view('user.links.index', compact('links', 'projects', 'summary'));
    }

    public function create(Request $request)
    {
        // Step 1 of the create-link flow: choose a name + type. The user
        // continues to a focused, type-specific Step 2 form.
        //
        // Pre-select the type the user last picked (per-user, stored in session)
        // so power users who repeatedly create the same kind of link can fly
        // through Step 1 with a single click + name.
        $lastType = $request->session()->get('links.last_type');
        if (!in_array($lastType, ['url', 'biolink', 'conversational', 'slides', 'ai_chat', 'restaurant_menu', 'store_menu', 'service_booking', 'file', 'ics', 'vcf'], true)) {
            $lastType = null;
        }

        $user           = $request->user();
        $aliasLimits    = $user->getAliasLengthLimits();
        $primaryDomain  = $user->domains()->where('is_verified', true)
                               ->orderBy('id')->first();
        $domainHost     = $primaryDomain->domain ?? $request->getHost();

        // When the AI builder is off, the card now renders as a teaser
        // instead of disappearing. Admins who can manage settings get an
        // "Enable AI" CTA to the engine settings; everyone else gets an
        // "Upgrade" CTA so the capability stays discoverable.
        $admin = $user->adminAccount();
        $aiBuilderAdminCanEnable = $admin !== null
            && $admin->status === 'active'
            && $admin->hasPermission('settings.manage');

        return view('user.links.create', [
            'prefillAlias' => (string) $request->query('alias', ''),
            'lastType'     => $lastType,
            'aliasLimits'  => $aliasLimits,
            'domainHost'   => $domainHost,
            'aiBuilderEnabled' => \App\Services\AI\AiEngineSettings::isEnabled(),
            'aiBuilderAdminCanEnable' => $aiBuilderAdminCanEnable,
        ]);
    }

    /**
     * Step 1 → Step 2 router. Validates the chosen type and forwards the
     * custom alias via query string to the right type-specific create form.
     * If the user left it blank, no `alias` param is forwarded and Step 2
     * (or `Link::generateAlias`) will produce one automatically.
     */
    public function chooseType(Request $request)
    {
        $limits = workspace_owner()->getAliasLengthLimits();

        $validated = $request->validate([
            'type'  => 'required|in:url,biolink,conversational,slides,ai_chat,restaurant_menu,store_menu,service_booking,file,ics,vcf,reviews,resume,paid_page,calendar,brand_kit',
            'alias' => [
                'nullable', 'string', new \App\Modules\User\Rules\AliasFormat(),
                'min:' . $limits['min'],
                'max:' . $limits['max'],
                new \App\Modules\User\Rules\UniqueAliasCi(),
                new \App\Modules\Admin\Rules\NotBannedName(),
            ],
        ]);

        $alias  = $validated['alias'] ?? null;
        $params = $alias !== null && $alias !== '' ? ['alias' => $alias] : [];

        // Remember this type for next time so the user's most-used flow gets
        // pre-selected on their next visit to Step 1.
        $request->session()->put('links.last_type', $validated['type']);

        // Conversational / Slides / AI Chatbot share the biolink "Step 2"
        // form (name + alias + project); the chosen type is carried through
        // so store() persists the right links.type.
        $params['type'] = $validated['type'];

        return match ($validated['type']) {
            'url'            => redirect()->route('user.links.url.create', $params),
            'biolink',
            'conversational',
            'slides',
            'ai_chat',
            'restaurant_menu',
            'store_menu'      => redirect()->route('user.links.biolink.create', $params),
            'service_booking' => redirect()->route('user.links.biolink.create', $params),
            'file'           => redirect()->route('user.links.file.create', $params),
            'ics'            => redirect()->route('user.links.ics.create', $params),
            'vcf'            => redirect()->route('user.links.vcf.create', $params),
            'reviews'        => redirect()->route('user.links.reviews.create', $params),
            'resume'         => redirect()->route('user.links.resume.create', $params),
            'paid_page'      => redirect()->route('user.links.paid-page.create', $params),
            'brand_kit'      => redirect()->route('user.links.brand-kit.create', $params),
            'calendar'       => redirect()->route('user.calendars.create', $params),
        };
    }

    /**
     * Lightweight JSON endpoint backing the live "Custom URL availability"
     * indicator on the Create Link page. Mirrors the exact alias rules used by
     * chooseType() and the wizard's captureCustomAlias() (alpha_dash, the
     * user's plan length limits, unique:links,alias, and the admin banned-names
     * list) so what the indicator shows matches what those flows enforce on
     * submit. A blank alias is the auto-generate case (no error).
     */
    public function checkAlias(Request $request)
    {
        $alias = (string) $request->query('alias', (string) $request->input('alias', ''));

        return response()->json(
            \App\Modules\User\Support\AliasAvailability::check(workspace_owner(), $alias)
        );
    }

    /**
     * Step 2 for the standalone Paid Page — name + alias + project + a
     * starting template. The link bridges to the creator's existing
     * monetized feed (posts / tiers / PPV / tipping); the dedicated
     * paid-page editor takes over after creation.
     */
    public function createPaidPage(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.create-paid-page', [
            'projects' => $projects,
            'domains'  => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
            'templates' => \App\Modules\User\Support\PaidPageTemplates::all(),
        ]);
    }

    /**
     * Step 2 for the standalone Brand / Press Kit page — name + alias +
     * project only. On store() the link is seeded from the owner's saved AI
     * Brand Kit (palette / fonts / voice / taglines / bio) into
     * settings['brand_kit'] and the user drops into the dedicated brand-kit
     * editor. AI generation is out of scope; this consumes a saved kit.
     */
    public function createBrandKit(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        $kits = \App\Modules\User\Models\BrandKit::where('user_id', workspace_owner_id())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_default']);

        return view('user.links.create-brand-kit', [
            'projects' => $projects,
            'domains'  => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
            'kits' => $kits,
        ]);
    }

    /**
     * Step 2 for the standalone Reviews page — name + alias + project only,
     * then the dedicated reviews editor takes over.
     */
    public function createReviews(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.create-reviews', [
            'projects' => $projects,
            'domains'  => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
        ]);
    }

    /**
     * Step 2 for the standalone Resume / Portfolio page — name + alias +
     * project only. The link is associated with the owner's existing resume
     * builder record on store(); the user then drops into the dedicated
     * resume editor (no parallel editor is built here).
     */
    public function createResume(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.create-resume', [
            'projects' => $projects,
            'domains'  => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
        ]);
    }

    /**
     * Step 2 for URL Shortener — focused form with only URL-relevant fields.
     */
    public function createUrl(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $pixels = workspace_owner()->pixels()->orderBy('name')->get();
        $domains = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.create-url', [
            'projects' => $projects,
            'pixels' => $pixels,
            'domains' => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
        ]);
    }

    /**
     * Step 2 for Bio Link — name + project only, then the existing
     * template picker / biolink editor takes over.
     */
    public function createBiolink(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        // Shared Step 2 form for the whole biolink family. The type is
        // carried through from the picker so store() persists it; default
        // to the classic biolink when missing or out of family.
        $type = (string) $request->query('type', 'biolink');
        if (!in_array($type, ['biolink', 'conversational', 'slides', 'ai_chat', 'restaurant_menu', 'store_menu', 'service_booking'], true)) {
            $type = 'biolink';
        }

        return view('user.links.create-biolink', [
            'projects' => $projects,
            'domains'  => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias' => (string) $request->query('alias', ''),
            'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
            'linkType'    => $type,
        ]);
    }

    /**
     * Enforce per-link-type plan toggles + numeric caps for the newer page
     * types (conversational, slides, ai_chat, restaurant_menu, reviews).
     *
     * Returns a user-facing error string when creation should be blocked, or
     * null when the type is allowed (or isn't one of the gated types). These
     * caps are INDEPENDENT of the family-wide `max_biolinks` limit (which
     * still counts the biolink-family types), so a user is bound by whichever
     * is stricter. Absent plan keys default to "enabled / unlimited" so plans
     * that predate this feature keep working until an admin sets explicit
     * values. Super-admin plan-bypass is honored via getPlanFeature/
     * planUnderLimit.
     */
    private function enforceLinkTypeQuota($owner, string $type): ?string
    {
        $map = [
            'conversational'  => ['module' => 'module_conversational',  'cap' => 'max_conversational',  'label' => 'Conversational'],
            'slides'          => ['module' => 'module_slides',          'cap' => 'max_slides',          'label' => 'Slides'],
            'ai_chat'         => ['module' => 'module_ai_chat',         'cap' => 'max_ai_chat',         'label' => 'AI Chatbot'],
            'restaurant_menu' => ['module' => 'module_restaurant_menu', 'cap' => 'max_restaurant_menu', 'label' => 'Restaurant Menu'],
            'store_menu' => ['module' => 'module_store_menu', 'cap' => 'max_store_menu', 'label' => 'Store Menu'],
            'service_booking' => ['module' => 'module_service_booking', 'cap' => 'max_service_booking', 'label' => 'Service Booking'],
            'reviews'         => ['module' => 'module_reviews',         'cap' => 'max_reviews',         'label' => 'Reviews'],
            'resume'          => ['module' => 'module_resume',          'cap' => 'max_resume',          'label' => 'Resume / Portfolio'],
            'paid_page'       => ['module' => 'module_paid_page',       'cap' => 'max_paid_page',       'label' => 'Bizs Profile'],
            'calendar'        => ['module' => 'module_calendar',        'cap' => 'max_calendars',       'label' => 'Calendar'],
            'brand_kit'       => ['module' => 'module_brand_kit',       'cap' => 'max_brand_kit_pages', 'label' => 'Brand / Press Kit'],
        ];
        $cfg = $map[$type] ?? null;
        if (!$cfg) {
            return null;
        }

        // Toggle: absent => enabled.
        if (!$owner->getPlanFeature($cfg['module'], true)) {
            return "{$cfg['label']} pages aren't available on your current plan. Upgrade to enable them.";
        }

        // Numeric cap: absent => unlimited (-1).
        $count = $owner->links()->where('type', $type)->count();
        if (!$owner->planUnderLimit($cfg['cap'], $count, -1)) {
            $max = (int) $owner->getPlanFeature($cfg['cap'], -1);
            return "You've reached your plan's {$cfg['label']} page limit ({$max}). Upgrade your plan for more.";
        }

        return null;
    }

    public function store(Request $request)
    {
        $userId = workspace_owner_id();

        $validated = $request->validate([
            'type' => 'required|in:url,biolink,conversational,slides,ai_chat,restaurant_menu,store_menu,service_booking,file,ics,vcf,reviews,resume,paid_page,calendar,brand_kit',
            'paid_page_template' => 'nullable|string|in:' . implode(',', \App\Modules\User\Support\PaidPageTemplates::ids()),
            'brand_kit_id' => "nullable|integer|exists:brand_kits,id,user_id,{$userId}",
            'long_url' => 'required_if:type,url|nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'alias' => array_merge(
                ['nullable', 'string', new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\User\Rules\UniqueAliasCi()],
                ['min:' . workspace_owner()->getAliasLengthLimits()['min']],
                ['max:' . workspace_owner()->getAliasLengthLimits()['max']],
                [new \App\Modules\Admin\Rules\NotBannedName()],
            ),
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'expires_at' => 'nullable|date|after:now',
            'expiry_url' => 'nullable|url:http,https|max:2048',
            'max_clicks' => 'nullable|integer|min:0|max:1000000000',
            'start_at'   => 'nullable|date',
            'expire_on_first_click' => 'nullable|boolean',
            'open_in_app' => 'nullable|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => \App\Services\UploadPolicy::rule('link.seo_image', $request->user()),
            'favicon' => \App\Services\UploadPolicy::rule('link.favicon', $request->user()),
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
            'smart_rules_json' => 'nullable|string|max:20000',
            'visibility' => 'nullable|in:public,registered,followers,subscribers',
        ] + self::protectionSchedulingRules());

        if (empty($validated['alias'])) {
            $validated['alias'] = Link::generateAlias();
        }

        // Per-link advanced setting gates — surfaced inline so the user sees
        // the upgrade nudge instead of a silent feature drop. We also check
        // protection-and-scheduling fields here (consumed later by
        // applyProtectionScheduling) so a downgraded plan cannot bypass.
        $owner = workspace_owner();

        // Per-link-type plan caps + on/off toggles for the newer page types
        // (conversational / slides / ai_chat / restaurant_menu / reviews).
        if ($typeError = $this->enforceLinkTypeQuota($owner, $validated['type'])) {
            return back()->withInput()->with('error', $typeError);
        }

        $gateMap = [
            'password'         => !empty($validated['password']),
            'expiry'           => self::isExpiryRequested($request),
            'geo_targeting'    => !empty($validated['country_restrictions']) || $request->filled('country_blocklist'),
            'device_targeting' => !empty($validated['device_targeting']),
            'deep_link'        => array_key_exists('open_in_app', $validated) && $request->boolean('open_in_app'),
            'smart_rules'      => !empty($validated['smart_rules_json']),
            'active_window'    => !empty($validated['start_at']) || $request->boolean('active_window_enabled'),
        ];
        foreach ($gateMap as $setting => $requested) {
            if ($requested && !$owner->userCanUseLinkSetting($setting)) {
                return back()->withInput()->with('error', 'The "' . str_replace('_', ' ', $setting) . '" link setting isn\'t available on your current plan. Upgrade to enable it.');
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        }

        try {
            if ($request->hasFile('seo_image')) {
                // OG / share image — photographic, downscale aggressively.
                $validated['seo_image'] = UserFile::createFromUpload($request->file('seo_image'), $request->user(), [
                    'compress_image' => true,
                    'max_width'      => 1200,
                    'max_height'     => 1200,
                    'quality'        => 85,
                ])->url;
            }
            if ($request->hasFile('favicon')) {
                // Favicons are tiny / pixel-perfect — store as-is.
                $validated['favicon'] = UserFile::createFromUpload($request->file('favicon'), $request->user())->url;
            }
        } catch (\RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        }

        $settings = [];
        if (!empty($validated['country_restrictions'])) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        }
        if ($request->boolean('show_preview_page') && in_array($validated['type'], ['url', 'ics', 'vcf'], true)) {
            $settings['show_preview_page'] = true;
        }
        // Scheduling / limits / app opener — same shape as update().
        if (!empty($validated['expiry_url']))     $settings['expiry_url'] = $validated['expiry_url'];
        if (!empty($validated['max_clicks']))     $settings['max_clicks'] = (int) $validated['max_clicks'];
        if (!empty($validated['start_at']))       $settings['start_at']   = $validated['start_at'];
        if (!empty($validated['expire_on_first_click'])) $settings['expire_on_first_click'] = true;
        // Default `open_in_app` to true for url-type links so app opening
        // is on by default — users can disable it per-link. The default
        // must respect the plan gate: if the user's plan disallows the
        // deep-link feature, force it off rather than silently enabling
        // a paid capability when the field is omitted.
        if (($validated['type'] ?? null) === 'url') {
            $deepLinkAllowed = $owner->userCanUseLinkSetting('deep_link');
            if ($request->has('open_in_app')) {
                $settings['open_in_app'] = $deepLinkAllowed && $request->boolean('open_in_app');
            } else {
                $settings['open_in_app'] = $deepLinkAllowed;
            }
        }
        // Smart redirect rules — supported on every link type. For non-url
        // types a matched rule overrides the normal landing/file behavior
        // with the rule's destination URL (see RedirectController::handle).
        if (!empty($validated['smart_rules_json'])) {
            $rules = $this->sanitizeSmartRules($validated['smart_rules_json']);
            if (!empty($rules)) $settings['smart_rules'] = $rules;
        }
        $validated['settings'] = !empty($settings) ? $settings : null;
        unset(
            $validated['country_restrictions'], $validated['device_targeting'],
            $validated['expiry_url'], $validated['max_clicks'], $validated['start_at'],
            $validated['expire_on_first_click'], $validated['open_in_app'],
            $validated['smart_rules_json']
        );

        $validated['user_id'] = workspace_owner_id();

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link = Link::create($validated);

        // Resume / Portfolio links bridge to the user's standalone resume
        // builder record. Associate the owner's default resume so the public
        // page and PDF export resolve through the existing renderer.
        if ($link->type === 'resume') {
            $resume = workspace_owner()->ensureResume();
            $link->resume_id = $resume->id;
            $link->save();
        }

        // Paid Page links bridge to the creator's existing monetized feed
        // (posts / tiers / PPV / tipping). Seed the chosen starting template
        // into settings['paid_page'] and default the page to public; the
        // dedicated editor lets the owner switch template + gate the page.
        if ($link->type === 'paid_page') {
            $tpl = $request->input('paid_page_template');
            if (!in_array($tpl, \App\Modules\User\Support\PaidPageTemplates::ids(), true)) {
                $tpl = \App\Modules\User\Support\PaidPageTemplates::DEFAULT_ID;
            }
            $settings = $link->settings ?? [];
            $settings['paid_page'] = ['template' => $tpl];
            $link->settings = $settings;
            $link->visibility = 'public';
            $link->save();
        }

        // Brand / Press Kit links seed their per-link config from the owner's
        // saved AI Brand Kit (palette / fonts / voice / taglines / bio) so the
        // page is presentable the moment it is created; the dedicated editor
        // lets the owner refine it. Pages default to public. AI generation is
        // out of scope — this consumes a kit the user already saved.
        if ($link->type === 'brand_kit') {
            $kit = null;
            $requestedKitId = $request->integer('brand_kit_id');
            if ($requestedKitId) {
                $kit = \App\Modules\User\Models\BrandKit::where('user_id', workspace_owner_id())
                    ->find($requestedKitId);
            }
            if (!$kit) {
                $kit = \App\Modules\User\Models\BrandKit::where('user_id', workspace_owner_id())
                    ->orderByDesc('is_default')
                    ->orderByDesc('id')
                    ->first();
            }
            $settings = $link->settings ?? [];
            $settings['brand_kit'] = \App\Modules\User\Support\BrandKitPageTemplates::prefillFromKit($kit, workspace_owner());
            $link->settings = $settings;
            $link->visibility = 'public';
            $link->save();
        }

        // Calendar links bridge 1:1 to a followable Calendar collection. Seed
        // the owner-authored title / timezone / accent and default the page to
        // public so it is discoverable + followable; the dedicated calendar
        // editor takes over to add events.
        if ($link->type === 'calendar') {
            $tz = (string) $request->input('calendar_timezone', '');
            if ($tz === '' || !in_array($tz, timezone_identifiers_list(), true)) {
                $tz = \App\Support\PlatformTimezone::forUser(workspace_owner());
            }
            $accent = (string) $request->input('calendar_accent_color', '#3d6bff');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                $accent = '#3d6bff';
            }

            \App\Modules\User\Models\Calendar::create([
                'link_id'     => $link->id,
                'user_id'     => $link->user_id,
                'title'       => $link->title ?: 'My Calendar',
                'slug'        => $link->alias,
                'description' => (string) $request->input('calendar_description', ''),
                'timezone'    => $tz,
                'accent_color' => $accent,
                'is_public'   => true,
            ]);
            $link->calendar_id = \App\Modules\User\Models\Calendar::where('link_id', $link->id)->value('id');
            $link->visibility = 'public';
            $link->save();
        }

        if (!empty($pixelIds)) {
            $link->pixels()->sync($pixelIds);
        }

        // Push a "link_published" feed event so followers see the new link.
        if (($link->is_active ?? true) && in_array($link->type, array_merge(Link::BIOLINK_FAMILY, ['short', 'file', 'splash', 'rsvp']))) {
            try {
                $u = auth()->user();
                \App\Modules\User\Models\FeedEvent::create([
                    'user_id'      => $link->user_id,
                    'type'         => 'link_published',
                    'subject_id'   => $link->id,
                    'subject_type' => \App\Modules\User\Models\Link::class,
                    'data'         => [
                        'title'           => $link->title,
                        'alias'           => $link->alias,
                        'creator_name'    => $u?->name,
                        'creator_avatar'  => $u?->avatar,
                    ],
                    'occurred_at'  => now(),
                ]);
                if ($u) {
                    \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced($u, 'published a new link: ' . ($link->title ?: $link->alias));
                }
            } catch (\Throwable $e) { \Log::warning('feed event failed: ' . $e->getMessage()); }
        }

        // Biolink-family types each open straight into their dedicated
        // editor instead of the generic links index.
        if ($link->type === 'conversational') {
            return redirect()->route('user.links.conversational.editor', $link)
                ->with('success', 'Conversational link created — build your chat flow.');
        }
        if ($link->type === 'slides') {
            return redirect()->route('user.links.slides.editor', $link)
                ->with('success', 'Slides link created — build your deck.');
        }
        if ($link->type === 'ai_chat') {
            return redirect()->route('user.links.ai-chat.editor', $link)
                ->with('success', 'AI Chatbot created — configure its persona and knowledge.');
        }
        if ($link->type === 'store_menu') {
            return redirect()->route('user.links.store.editor', $link)
                ->with('status', 'Store created. Build your catalog below.');
        }

        if ($link->type === 'restaurant_menu') {
            return redirect()->route('user.links.restaurant.editor', $link)
                ->with('success', 'Restaurant Menu created — build your menu.');
        }
        if ($link->type === 'service_booking') {
            return redirect()->route('user.links.service-booking.editor', $link)
                ->with('success', 'Service Booking created — add your services and availability.');
        }
        if ($link->type === 'reviews') {
            return redirect()->route('user.links.reviews.editor', $link)
                ->with('success', 'Reviews page created — configure it and start collecting reviews.');
        }
        if ($link->type === 'resume') {
            return redirect()->route('user.resume.editor')
                ->with('success', 'Resume / Portfolio link created — build and publish your resume.');
        }
        if ($link->type === 'paid_page') {
            return redirect()->route('user.links.paid-page.editor', $link)
                ->with('success', 'Bizs Profile created — just pick a design. Your posts and tiers appear here automatically; there is no linking step.');
        }
        if ($link->type === 'brand_kit') {
            return redirect()->route('user.links.brand-kit.editor', $link)
                ->with('success', 'Brand / Press Kit created — we filled it in from your saved kit. Review and publish.');
        }
        if ($link->type === 'calendar') {
            return redirect()->route('user.calendars.editor', $link)
                ->with('success', 'Calendar created — add your events and share it so people can follow.');
        }

        // "Build with AI" start mode — skip the picker and send the user to
        // the AI page builder intake, where they describe the page and the
        // assistant assembles it from real supported block types.
        if ($link->type === 'biolink'
            && $request->input('start_mode') === 'ai'
            && \App\Services\AI\AiEngineSettings::isEnabled()) {
            return redirect()->route('user.links.ai-builder', $link)
                ->with('success', 'Link in Bio created — describe it and let AI build your page.');
        }

        // For new biolinks, send the user to the template picker so they can
        // start from an admin-curated preset (or skip and start from scratch).
        // Always send new biolinks to the picker when any active templates
        // exist — locked ones are still shown with an upgrade CTA so users
        // can discover premium presets.
        if ($link->type === 'biolink' && \App\Modules\Admin\Models\PageTemplate::active()->exists()) {
            return redirect()->route('user.links.templates.picker', $link)
                ->with('success', 'Link in Bio created — pick a template to start, or skip.');
        }

        return redirect()->route('user.links.index')
            ->with('success', 'Link created successfully.');
    }

    public function show(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $link->load(['project', 'domain', 'pixels']);

        [$startDate, $endDate, $period, $groupBy] = $this->resolveAnalyticsRange($request);

        // Optional per-alias filter — narrow analytics to clicks that came in
        // through a specific alias (e.g. "?alias=summer-promo").
        $aliasFilter = $request->query('alias');
        $availableAliases = $link->getAllAliases();
        if ($aliasFilter && !in_array($aliasFilter, $availableAliases, true)) {
            $aliasFilter = null;
        }

        // Per-alias breakdown (counts BEFORE applying alias filter so the user can
        // see all aliases and switch between them).
        $aliasBreakdown = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->whereNotNull('alias')
            ->selectRaw('alias, COUNT(*) as total')
            ->groupBy('alias')
            ->orderByDesc('total')
            ->get();

        // Optional traffic-source filter — narrow analytics to clicks logged
        // from a particular surface (mobile_app vs web). Kept un-applied to
        // $sourceStats below so the breakdown card always shows the full split.
        $sourceFilter = $request->query('source');
        if (!in_array($sourceFilter, ['mobile_app', 'web'], true)) {
            $sourceFilter = null;
        }

        // Optional country filter — narrow analytics to clicks from a specific
        // country code (ISO 3166-1 alpha-2). Kept un-applied to $countryStats
        // below so the breakdown card always shows the full split.
        $countryFilter = $request->query('country');
        if (is_string($countryFilter) && preg_match('/^[A-Za-z]{2}$/', $countryFilter)) {
            $countryFilter = strtoupper($countryFilter);
        } else {
            $countryFilter = null;
        }

        // Optional device filter — narrow analytics to a specific device type.
        // Kept un-applied to $deviceStats below so users see the full split.
        $deviceFilter = $request->query('device');
        if (!in_array($deviceFilter, ['mobile', 'desktop', 'tablet'], true)) {
            $deviceFilter = null;
        }

        // Optional browser / OS / language filters — narrow analytics to clicks
        // matching that user-agent dimension. Each is intentionally NOT applied
        // to its own breakdown card below so users see the full split and can
        // switch between values. These are free-form strings (whatever the
        // user-agent parser stored), so we trim / cap length rather than
        // whitelisting against a fixed set.
        $sanitizeUaFilter = function ($v) {
            if (!is_string($v)) return null;
            $v = trim($v);
            if ($v === '' || mb_strlen($v) > 64) return null;
            return $v;
        };
        $browserFilter  = $sanitizeUaFilter($request->query('browser'));
        $osFilter       = $sanitizeUaFilter($request->query('os'));
        $languageFilter = $sanitizeUaFilter($request->query('language'));

        // Optional channel filter — narrow analytics to clicks classified
        // into a specific in-app webview / browser bucket (Sayzio app,
        // Instagram, generic webview, regular browser, bot, …). Like the
        // other dimension filters this is intentionally NOT applied to its
        // own breakdown card so users always see the full split.
        $channelFilter = $request->query('channel');
        if (!is_string($channelFilter) || !in_array($channelFilter, \App\Modules\Common\Services\ChannelClassifier::validKeys(), true)) {
            $channelFilter = null;
        }

        // Optional base-language filter — narrow analytics to clicks whose
        // browser locale shares this base language (e.g. "en" matches en-US,
        // en-GB, en_CA, …). Lets users drill into the rolled-up "By language"
        // pills in the Languages card. Stored locales are free-form strings
        // (BCP-47 tags, sometimes with `_` separators), so we normalize at
        // query time. Only valid 2- or 3-letter base codes are accepted.
        $baseLanguageFilter = $sanitizeUaFilter($request->query('lang_base'));
        if ($baseLanguageFilter !== null) {
            $baseLanguageFilter = strtolower($baseLanguageFilter);
            if (!preg_match('/^[a-z]{2,3}$/', $baseLanguageFilter)) {
                $baseLanguageFilter = null;
            }
        }
        $applyBaseLanguage = fn ($q) => $q->whereRaw(
            "LOWER(SPLIT_PART(REPLACE(language, '_', '-'), '-', 1)) = ?",
            [$baseLanguageFilter]
        );

        $clicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($channelFilter, fn ($q) => $q->where('channel', $channelFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage);

        $totalInRange = (clone $clicksQuery)->count();
        $uniqueInRange = (clone $clicksQuery)->distinct('ip_address')->count('ip_address');
        $blockClicksInRange = (clone $clicksQuery)->whereNotNull('block_id')->count();
        $pageVisitsInRange = (clone $clicksQuery)->whereNull('block_id')->count();

        // Poll-vote engagement for this link in the selected date range. Surfaced
        // as a headline KPI alongside clicks so creators see polls in their main
        // numbers, with a per-poll breakdown card linking to the existing
        // per-block poll-votes page for drill-down.
        $pollVotesQuery = PollVote::where('link_id', $link->id)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $pollVotesInRange = (clone $pollVotesQuery)->count();

        $pollVoteCountsByBlock = (clone $pollVotesQuery)
            ->selectRaw('block_id, COUNT(*) as vote_count')
            ->groupBy('block_id')
            ->pluck('vote_count', 'block_id')
            ->all();

        // Always pull every poll block on this link so the breakdown surfaces
        // zero-vote polls too. That keeps Poll Engagement a first-class metric
        // (visible whenever the link has any poll blocks), not just when
        // someone has already voted.
        $pollBlocks = \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
            ->where('type', 'poll')
            ->get(['id', 'settings']);
        $hasPollBlocks = $pollBlocks->isNotEmpty();
        $pollBreakdown = $pollBlocks->map(function ($blk) use ($pollVoteCountsByBlock) {
            $settings = $blk->settings ?? [];
            return (object) [
                'block_id' => $blk->id,
                'question' => $settings['question'] ?? 'Poll',
                'votes'    => (int) ($pollVoteCountsByBlock[$blk->id] ?? 0),
            ];
        })->sortByDesc('votes')->values();

        // Count of bot/scraper clicks that the global scope filtered out of the
        // numbers above. Surfaced on the analytics page as a small "X bot hits
        // filtered" badge so creators understand drops in popular-link traffic
        // aren't missing data — they're crawlers we deliberately exclude.
        // Mirrors the same dimension filters as $clicksQuery for consistency.
        $botClicksQuery = \App\Modules\User\Models\LinkClick::withBots()
            ->where('link_id', $link->id)
            ->where('is_bot', true)
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($channelFilter, fn ($q) => $q->where('channel', $channelFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage);

        $botClicksInRange = (clone $botClicksQuery)->count();

        // Group raw bot UAs and bucket them into friendly families (Googlebot,
        // ClaudeBot, Headless Chrome, …) so the "Bot hits filtered" badge can
        // reveal *which* bots are hitting this link, not just how many. We pull
        // the top N raw UAs (cheap, indexed-ish group-by) and aggregate into
        // families in PHP — UA cardinality is low enough that this is fine and
        // it lets us reuse the existing BotDetector classification logic.
        $botFamilyBreakdown = collect();
        if ($botClicksInRange > 0) {
            $rawBotUserAgents = (clone $botClicksQuery)
                ->selectRaw('COALESCE(user_agent, \'\') as ua, COUNT(*) as count')
                ->groupBy('ua')
                ->orderByDesc('count')
                ->limit(200)
                ->get();

            $detector = app(\App\Modules\Common\Services\BotDetector::class);
            $families = [];
            // Track one representative raw UA per family so creators can see
            // *what* is hitting them — especially useful for the catch-all
            // "Other …" buckets. We pick the highest-count UA in each family
            // (rawBotUserAgents is already sorted by count desc, so the first
            // UA we see for a family is the most frequent one).
            $sampleUserAgents = [];
            foreach ($rawBotUserAgents as $row) {
                $family = $detector->classifyFamily($row->ua === '' ? null : $row->ua);
                $families[$family] = ($families[$family] ?? 0) + (int) $row->count;
                if (!isset($sampleUserAgents[$family]) && $row->ua !== '') {
                    $sampleUserAgents[$family] = $row->ua;
                }
            }
            arsort($families);
            $botFamilyBreakdown = collect($families)
                ->map(fn ($count, $family) => (object) [
                    'family' => $family,
                    'count' => $count,
                    'sample_user_agent' => $sampleUserAgents[$family] ?? null,
                ])
                ->values();
        }

        $dateExpr = match ($groupBy) {
            'week' => "TO_CHAR(DATE_TRUNC('week', clicked_at), 'YYYY-MM-DD')",
            'month' => "TO_CHAR(DATE_TRUNC('month', clicked_at), 'YYYY-MM')",
            'year' => "TO_CHAR(DATE_TRUNC('year', clicked_at), 'YYYY')",
            default => "TO_CHAR(DATE_TRUNC('day', clicked_at), 'YYYY-MM-DD')",
        };

        $clicksOverTime = (clone $clicksQuery)
            ->selectRaw("$dateExpr as bucket, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->groupByRaw($dateExpr)
            ->orderBy('bucket')
            ->get();

        // Bot hits per bucket — same period/dimension filters as the main chart,
        // but bypasses the global "no bots" scope so creators can spot scraper
        // spikes over time alongside the human series.
        $botClicksOverTime = \App\Modules\User\Models\LinkClick::withBots()
            ->where('link_id', $link->id)
            ->where('is_bot', true)
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($channelFilter, fn ($q) => $q->where('channel', $channelFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("$dateExpr as bucket, COUNT(*) as count")
            ->groupByRaw($dateExpr)
            ->orderBy('bucket')
            ->pluck('count', 'bucket');

        $topReferrers = (clone $clicksQuery)
            ->selectRaw("referrer, COUNT(*) as count")
            ->whereNotNull('referrer')->where('referrer', '!=', '')
            ->groupBy('referrer')->orderByDesc('count')->limit(10)->get();

        // Intentionally NOT filtered by $browserFilter so the card always shows
        // the full split (and lets users switch between browsers).
        $browserStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("browser, COUNT(*) as count")
            ->whereNotNull('browser')->groupBy('browser')->orderByDesc('count')->get();

        // Intentionally NOT filtered by $osFilter so the card always shows the
        // full split (and lets users switch between operating systems).
        $osStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("os, COUNT(*) as count")
            ->whereNotNull('os')->groupBy('os')->orderByDesc('count')->get();

        // Intentionally NOT filtered by $countryFilter so users can always see
        // the full country split (and switch between countries) on the card.
        $countryStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("country_code, COUNT(*) as count")
            ->whereNotNull('country_code')->groupBy('country_code')
            ->orderByDesc('count')->limit(20)->get();

        $cityStats = (clone $clicksQuery)
            ->selectRaw("city, country_code, COUNT(*) as count")
            ->whereNotNull('city')->groupBy('city', 'country_code')
            ->orderByDesc('count')->limit(20)->get();

        // Intentionally NOT filtered by $deviceFilter so users can always see
        // the full device split (and switch between devices) on the card.
        $deviceStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("device_type, COUNT(*) as count")
            ->whereNotNull('device_type')->groupBy('device_type')->orderByDesc('count')->get();

        // Mobile-app vs web traffic split. Rows logged before this column
        // existed are surfaced under "Unknown" so totals always reconcile.
        // Intentionally NOT filtered by $sourceFilter so users can always see
        // the full split (and switch between sources) on the breakdown card.
        $sourceStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("COALESCE(source, 'unknown') as source, COUNT(*) as count")
            ->groupBy('source')->orderByDesc('count')->get();

        // Channel breakdown — derived from the user-agent classifier so
        // creators can tell genuine in-app traffic (Instagram, TikTok,
        // Facebook, our own native shell, …) apart from real browsers and
        // bots. Older rows that pre-date the column show under 'unknown'.
        // Intentionally NOT filtered by $channelFilter so the breakdown
        // always shows the full split (and lets users switch buckets).
        $channelStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage)
            ->selectRaw("COALESCE(channel, 'unknown') as channel, COUNT(*) as count")
            ->groupBy('channel')->orderByDesc('count')->get();

        // Intentionally NOT filtered by $languageFilter so the card always
        // shows the full split (and lets users switch between languages).
        $languageStats = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->selectRaw("language, COUNT(*) as count")
            ->whereNotNull('language')->groupBy('language')
            ->orderByDesc('count')->limit(15)->get();

        $blockStats = (clone $clicksQuery)
            ->selectRaw("block_id, block_type, destination_url, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id', 'block_type', 'destination_url')
            ->orderByDesc('count')->limit(50)->get();

        // ---- Previous-period comparison (same span ending immediately before $startDate) ----
        $rangeSeconds  = max(1, $endDate->diffInSeconds($startDate));
        // Previous-period end is *exclusive* of the current-period start so a
        // click occurring exactly at $startDate is counted once, not twice.
        $prevEndDate   = (clone $startDate)->subSecond();
        $prevStartDate = (clone $startDate)->subSeconds($rangeSeconds);
        $prevClicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$prevStartDate, $prevEndDate])
            // Mirror the alias filter onto the previous-period query so vs-prev
            // deltas (KPI tiles, per-platform comparisons) are apples-to-apples.
            ->when($aliasFilter, fn ($q) => $q->where('alias', $aliasFilter))
            ->when($sourceFilter, fn ($q) => $q->where('source', $sourceFilter))
            ->when($countryFilter, fn ($q) => $q->where('country_code', $countryFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_type', $deviceFilter))
            ->when($browserFilter, fn ($q) => $q->where('browser', $browserFilter))
            ->when($osFilter, fn ($q) => $q->where('os', $osFilter))
            ->when($languageFilter, fn ($q) => $q->where('language', $languageFilter))
            ->when($channelFilter, fn ($q) => $q->where('channel', $channelFilter))
            ->when($baseLanguageFilter, $applyBaseLanguage);

        $totalInRangePrev         = (clone $prevClicksQuery)->count();
        $blockClicksInRangePrev   = (clone $prevClicksQuery)->whereNotNull('block_id')->count();
        $uniqueBlockClicksInRange = (clone $clicksQuery)->whereNotNull('block_id')->distinct('ip_address')->count('ip_address');
        $uniqueBlockClicksPrev    = (clone $prevClicksQuery)->whereNotNull('block_id')->distinct('ip_address')->count('ip_address');

        // Per-block previous-period counts (keyed by block_id) — used for KPI tiles
        $blockStatsPrev = (clone $prevClicksQuery)
            ->selectRaw("block_id, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id')
            ->get()
            ->keyBy('block_id');

        // Per-(block + destination) previous-period counts — needed so socials*
        // and other multi-link blocks get a real per-platform "vs prev" delta.
        $blockStatsPrevByDest = (clone $prevClicksQuery)
            ->selectRaw("block_id, destination_url, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->whereNotNull('block_id')
            ->groupBy('block_id', 'destination_url')
            ->get()
            ->mapWithKeys(fn($r) => [$r->block_id . '|' . ($r->destination_url ?? '') => $r]);

        $utmStats = (clone $clicksQuery)
            ->selectRaw("utm_params, COUNT(*) as count")
            ->whereNotNull('utm_params')
            ->groupBy('utm_params')->orderByDesc('count')->limit(15)->get();

        $recentClicks = (clone $clicksQuery)
            ->orderByDesc('clicked_at')
            ->paginate(25)
            ->withQueryString();

        // Engagement: page sessions
        $sessionsQuery = \App\Modules\User\Models\PageSession::where('link_id', $link->id)
            ->whereBetween('started_at', [$startDate, $endDate]);

        $totalSessions = (clone $sessionsQuery)->count();
        $avgSessionSeconds = (int) round((clone $sessionsQuery)->avg('duration_seconds') ?? 0);
        $totalEngagedSeconds = (int) ((clone $sessionsQuery)->sum('duration_seconds') ?? 0);
        $bounceSessions = (clone $sessionsQuery)->where('duration_seconds', '<', 5)->count();
        $bounceRate = $totalSessions > 0 ? round(($bounceSessions / $totalSessions) * 100, 1) : 0;

        // Engagement: per-block dwell time
        $blockEngagement = \App\Modules\User\Models\BlockView::where('link_id', $link->id)
            ->whereBetween('first_viewed_at', [$startDate, $endDate])
            ->selectRaw("block_id, block_type,
                SUM(view_duration_ms) as total_ms,
                AVG(view_duration_ms) as avg_ms,
                SUM(impression_count) as impressions,
                COUNT(DISTINCT session_id) as unique_viewers")
            ->groupBy('block_id', 'block_type')
            ->orderByDesc('total_ms')
            ->limit(50)
            ->get();

        // Map block clicks for CTR calc
        $blockClickMap = [];
        foreach ($blockStats as $b) { $blockClickMap[$b->block_id] = $b->count; }

        // Build a lightweight metadata map (title/url/thumb) for each block on this link
        // so the engagement table can show *what* each block actually is, not just an id.
        $blockMeta = [];
        if ($link->isBiolinkFamily()) {
            $blocksForLink = \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                ->get(['id', 'type', 'settings', 'parent_id', 'is_active']);

            $titleKeys = ['title', 'heading', 'text', 'label', 'name', 'caption', 'question', 'button_text', 'description', 'bio', 'code'];
            $urlKeys   = ['url', 'link', 'destination_url', 'href', 'embed_url', 'src', 'video_url'];
            $imgKeys   = ['image', 'image_url', 'thumbnail', 'avatar', 'logo', 'icon_url', 'poster', 'src', 'url'];

            // Recursively find the first non-empty string value for any of $keys
            $findString = function ($data, array $keys, int $depth = 0) use (&$findString) {
                if ($depth > 4 || !is_array($data)) return null;
                foreach ($keys as $k) {
                    if (!empty($data[$k]) && is_string($data[$k])) {
                        $v = trim(strip_tags($data[$k]));
                        if ($v !== '') return $v;
                    }
                }
                foreach ($data as $v) {
                    if (is_array($v)) {
                        $r = $findString($v, $keys, $depth + 1);
                        if ($r) return $r;
                    }
                }
                return null;
            };
            $findImage = function ($data, array $keys, int $depth = 0) use (&$findImage) {
                if ($depth > 4 || !is_array($data)) return null;
                foreach ($keys as $k) {
                    if (!empty($data[$k]) && is_string($data[$k]) && preg_match('~^(https?:|/)|\.(png|jpe?g|gif|webp|svg)(\?|$)~i', $data[$k])) {
                        return $data[$k];
                    }
                }
                foreach ($data as $v) {
                    if (is_array($v)) {
                        $r = $findImage($v, $keys, $depth + 1);
                        if ($r) return $r;
                    }
                }
                return null;
            };

            foreach ($blocksForLink as $blk) {
                $s = is_array($blk->settings) ? $blk->settings : (json_decode($blk->settings ?? '{}', true) ?: []);

                $title = null;
                // Socials should always render as "Socials: name1, name2..." not just "instagram"
                if ($blk->type === 'socials' && !empty($s['platforms']) && is_array($s['platforms'])) {
                    $names = [];
                    foreach (array_slice($s['platforms'], 0, 3) as $p) {
                        if (is_array($p)) $names[] = ucfirst($p['platform'] ?? $p['name'] ?? '');
                    }
                    $names = array_filter($names);
                    $more  = count($s['platforms']) - count($names);
                    $typeLabel = \App\Modules\User\Models\BiolinkBlock::TYPES['socials']['label'] ?? 'Socials';
                    $title = $typeLabel . ': ' . (empty($names) ? count($s['platforms']) . ' platforms' : implode(', ', $names) . ($more > 0 ? " +{$more}" : ''));
                } else {
                    $title = $findString($s, $titleKeys);
                    if ($title) $title = \Illuminate\Support\Str::limit($title, 60);
                }

                $url   = $findString($s, $urlKeys);
                $thumb = $findImage($s, $imgKeys);

                // Type-specific fallbacks for blocks that have no obvious text value
                if (!$title) {
                    $typeLabel = \App\Modules\User\Models\BiolinkBlock::TYPES[$blk->type]['label'] ?? ucfirst($blk->type);
                    if ($blk->type === 'socials' && !empty($s['platforms']) && is_array($s['platforms'])) {
                        $names = [];
                        foreach (array_slice($s['platforms'], 0, 3) as $p) {
                            if (is_array($p) && !empty($p['platform'])) $names[] = ucfirst($p['platform']);
                            elseif (is_array($p) && !empty($p['name'])) $names[] = $p['name'];
                        }
                        $more = count($s['platforms']) - count($names);
                        $title = $typeLabel . ': ' . (empty($names) ? count($s['platforms']) . ' platforms' : implode(', ', $names) . ($more > 0 ? " +{$more}" : ''));
                    } elseif (in_array($blk->type, ['faq', 'progress', 'list', 'list_numbered', 'list_pricing']) && !empty($s['items']) && is_array($s['items'])) {
                        $first = $s['items'][0] ?? null;
                        $firstText = is_array($first) ? ($first['question'] ?? $first['title'] ?? $first['label'] ?? $first['text'] ?? $first['name'] ?? null) : null;
                        $title = $typeLabel . ' (' . count($s['items']) . ($firstText ? '): "' . \Illuminate\Support\Str::limit(trim(strip_tags($firstText)), 35) . '"' : ' items)');
                    } else {
                        $title = $typeLabel;
                    }
                }

                // For multi-link blocks (socials*), expose a URL → platform map so the
                // analytics view can resolve each click row to the right platform name/icon.
                $platformsMap = [];
                if (in_array($blk->type, ['socials', 'socials_multi', 'socials_custom'])) {
                    $platList = $s['platforms'] ?? [];
                    if ($blk->type === 'socials_multi' && !empty($s['groups']) && is_array($s['groups'])) {
                        $platList = [];
                        foreach ($s['groups'] as $group) {
                            $platList = array_merge($platList, $group['platforms'] ?? []);
                        }
                    }
                    foreach ($platList as $p) {
                        if (!is_array($p)) continue;
                        $purl = $p['url'] ?? $p['link'] ?? null;
                        if (!$purl) continue;
                        $pname = strtolower(trim($p['name'] ?? $p['platform'] ?? ''));
                        $platformsMap[$purl] = [
                            'key'   => $pname,
                            'label' => $p['label'] ?? ($pname !== '' ? ucfirst($pname) : 'Link'),
                            'url'   => $purl,
                        ];
                    }
                }

                $blockMeta[$blk->id] = [
                    'title'     => $title,
                    'url'       => $url,
                    'thumb'     => $thumb,
                    'type'      => $blk->type,
                    'platforms' => $platformsMap,
                ];
            }
        }

        // ---- Performance Coach: derive a tiny block-inventory snapshot from
        // the `$blocksForLink` collection we already fetched above for
        // $blockMeta — no additional query. The coach engine itself issues
        // zero queries and simply transforms this context into insights.
        $blockInventory = ['clickable' => [], 'has_socials' => false, 'has_qr' => false,
                            'active_count' => 0, 'top_level_active_count' => 0,
                            'disabled_socials_block_id' => null];
        if ($link->isBiolinkFamily() && isset($blocksForLink)) {
            $nonInteractive = ['heading', 'heading_logo',
                'paragraph', 'paragraph_rich', 'divider', 'spacer',
                'verified_heading', 'verified_avatar', 'alert', 'badge', 'avatar'];
            $socialTypes = ['socials', 'socials_multi', 'socials_custom'];
            foreach ($blocksForLink as $blk) {
                $isSocial = in_array($blk->type, $socialTypes, true);
                if (!$blk->is_active) {
                    // Track a disabled socials block so the coach can offer a
                    // one-click "enable" action instead of the generic tip.
                    if ($isSocial && $blockInventory['disabled_socials_block_id'] === null) {
                        $blockInventory['disabled_socials_block_id'] = $blk->id;
                    }
                    continue;
                }
                $blockInventory['active_count']++;
                if ($blk->parent_id === null) {
                    $blockInventory['top_level_active_count']++;
                    if (!in_array($blk->type, $nonInteractive, true)) {
                        $blockInventory['clickable'][] = $blk->id;
                    }
                }
                if ($isSocial)              $blockInventory['has_socials'] = true;
                if ($blk->type === 'qr_code') $blockInventory['has_qr']    = true;
            }
        }

        // 30-day score history for the sparkline rendered in the coach card.
        // Rows are produced by the `coach:snapshot-scores` nightly command.
        $performanceHistory = \App\Modules\User\Models\LinkPerformanceSnapshot::where('link_id', $link->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get(['date', 'score', 'components_json'])
            ->map(fn ($r) => [
                'date'       => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : (string) $r->date,
                'score'      => (int) $r->score,
                'components' => is_array($r->components_json) ? $r->components_json : null,
            ])
            ->values()
            ->all();

        $performance = \App\Modules\User\Services\LinkPerformanceCoach::build([
            'link'               => $link,
            'totalInRange'       => $totalInRange,
            'uniqueInRange'      => $uniqueInRange,
            'blockClicksInRange' => $blockClicksInRange,
            'pageVisitsInRange'  => $pageVisitsInRange,
            'totalSessions'      => $totalSessions,
            'avgSessionSeconds'  => $avgSessionSeconds,
            'bounceRate'         => $bounceRate,
            'blockStats'         => $blockStats,
            'topReferrers'       => $topReferrers,
            'totalInRangePrev'   => $totalInRangePrev,
            'aliasFilter'        => $aliasFilter,
            'period'             => $period,
            'blockInventory'     => $blockInventory,
        ]);

        // Full block inventory (every block on the biolink, including
        // zero-click ones in this window) for the per-block drill-down
        // — required so EVERY block is clickable, not just clicked ones.
        $blockSummaryAll = BlockAnalyticsAggregator::blockSummary($link, $startDate, $endDate);

        return view('user.links.show', compact(
            'link', 'clicksOverTime', 'botClicksOverTime', 'topReferrers',
            'blockSummaryAll',
            'browserStats', 'osStats', 'countryStats', 'cityStats',
            'deviceStats', 'sourceStats', 'channelStats', 'languageStats', 'blockStats', 'utmStats',
            'recentClicks', 'totalInRange', 'uniqueInRange',
            'blockClicksInRange', 'pageVisitsInRange', 'botClicksInRange', 'botFamilyBreakdown',
            'pollVotesInRange', 'pollBreakdown', 'hasPollBlocks',
            'period', 'groupBy', 'startDate', 'endDate',
            'totalSessions', 'avgSessionSeconds', 'totalEngagedSeconds',
            'bounceRate', 'blockEngagement', 'blockClickMap', 'blockMeta',
            'blockStatsPrev', 'blockStatsPrevByDest', 'blockClicksInRangePrev',
            'totalInRangePrev',
            'uniqueBlockClicksInRange', 'uniqueBlockClicksPrev',
            'aliasBreakdown', 'aliasFilter', 'availableAliases', 'sourceFilter',
            'countryFilter', 'deviceFilter',
            'browserFilter', 'osFilter', 'languageFilter', 'baseLanguageFilter',
            'channelFilter',
            'performance', 'performanceHistory'
        ));
    }

    /**
     * Update the per-link Performance Coach tuning (preset or custom thresholds).
     * Thresholds are stored on the link's `settings` JSON under `performance_coach`
     * so they persist between visits and re-apply on the next coach build.
     */
    public function updatePerformanceCoachSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $validated = $request->validate([
            'preset' => ['required', 'string', 'in:' . implode(',', \App\Modules\User\Services\LinkPerformanceCoach::validPresetKeys())],
            'overrides' => 'nullable|array',
            'overrides.ctr_critical' => 'nullable|numeric|between:0,1',
            'overrides.ctr_warning' => 'nullable|numeric|between:0,1',
            'overrides.ctr_excellent' => 'nullable|numeric|between:0,1',
            'overrides.bounce_critical' => 'nullable|numeric|between:0,100',
            'overrides.bounce_warning' => 'nullable|numeric|between:0,100',
            'overrides.bounce_excellent' => 'nullable|numeric|between:0,100',
            'overrides.engagement_low_seconds' => 'nullable|numeric|between:1,600',
            'overrides.engagement_excellent_seconds' => 'nullable|numeric|between:1,600',
            'overrides.momentum_drop_critical' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_drop_warning' => 'nullable|numeric|between:-1,0',
            'overrides.momentum_win_threshold' => 'nullable|numeric|between:0,5',
        ]);

        \App\Modules\User\Services\LinkPerformanceCoach::saveLinkSettings(
            $link,
            $validated['preset'],
            $validated['overrides'] ?? []
        );

        return redirect()
            ->to(url()->previous() ?: route('user.links.show', $link))
            ->with('success', 'Performance Coach thresholds updated.');
    }

    private function resolveAnalyticsRange(Request $request): array
    {
        $period = $request->query('period', '30d');
        $groupBy = $request->query('group', 'day');
        if (!in_array($groupBy, ['day', 'week', 'month', 'year'])) $groupBy = 'day';

        $end = now()->endOfDay();
        $start = match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            'all' => now()->subYears(10)->startOfDay(),
            'custom' => $request->query('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };
        if ($period === 'custom' && $request->query('to')) {
            $end = \Carbon\Carbon::parse($request->query('to'))->endOfDay();
        }

        // Clamp the start of the range to the plan's stats-history retention so
        // users can't query analytics older than their plan allows (the data
        // beyond that window is pruned anyway). `-1` = unlimited (no clamp).
        $retentionDays = workspace_owner()->statsRetentionDays();
        if ($retentionDays !== -1) {
            $earliest = now()->subDays($retentionDays)->startOfDay();
            if ($start->lt($earliest)) {
                $start = $earliest;
            }
        }

        return [$start, $end, $period, $groupBy];
    }

    public function recentClicksPartial(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        // `$link->clicks()` flows through the LinkClick model and therefore
        // the `is_bot = false` global scope — bot/scraper rows never reach
        // the live activity table, matching the totals shown above it.
        $recentClicks = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->orderByDesc('clicked_at')
            ->paginate(25)
            ->withQueryString();

        $blockTypes = \App\Modules\User\Models\BiolinkBlock::TYPES;

        return view('user.links.partials.recent-clicks-table', compact('recentClicks', 'blockTypes'));
    }

    /**
     * Followers tab on the per-link analytics dashboard.
     *
     * Surfaces follower-specific cohort analytics for this link:
     *  - % of unique visitors (in period) that are followers of this creator
     *  - top 10 most-engaged followers (by click count on this link)
     *  - 30-day daily trend of follower vs non-follower clicks (re-uses the
     *    same Chart.js component as the main analytics chart for consistency)
     *
     * "Follower" = a user with a row in `follows` where creator_id = this
     * link's owner. A click "by a follower" is one whose viewer_user_id is
     * in that follower set.
     */
    public function followers(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $link->load(['project', 'domain']);
        [$startDate, $endDate, $period, $groupBy] = $this->resolveAnalyticsRange($request);

        // All follower IDs for this creator. Cheap lookup table for joins
        // and the IN-clause we use to mark click rows as follower clicks.
        $followerIds = Follow::where('creator_id', $link->user_id)
            ->pluck('follower_id');
        $totalFollowerCount = $followerIds->count();

        $clicksQuery = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate]);

        // Unique visitor cohort split — denominator is unique IPs (matches
        // the rest of the analytics dashboard); numerator is unique IPs
        // whose click was attributed to a known follower.
        $uniqueVisitors = (clone $clicksQuery)->distinct('ip_address')->count('ip_address');
        $uniqueFollowerVisitors = $totalFollowerCount === 0 ? 0
            : (clone $clicksQuery)
                ->whereIn('viewer_user_id', $followerIds)
                ->distinct('ip_address')->count('ip_address');
        $followerVisitorPct = $uniqueVisitors > 0
            ? round(($uniqueFollowerVisitors / $uniqueVisitors) * 100, 1) : 0;

        // Total click split (all clicks, including page-views and block clicks)
        $totalClicks = (clone $clicksQuery)->count();
        $followerClicks = $totalFollowerCount === 0 ? 0
            : (clone $clicksQuery)->whereIn('viewer_user_id', $followerIds)->count();
        $nonFollowerClicks = max(0, $totalClicks - $followerClicks);

        // 30-day (or selected-period) daily trend: follower vs non-follower
        // clicks. Buckets by DATE(clicked_at) so the chart can re-use the
        // existing Chart.js line component without any axis changes.
        $dateExpr = "TO_CHAR(DATE_TRUNC('day', clicked_at), 'YYYY-MM-DD')";
        $rawDaily = (clone $clicksQuery)
            ->selectRaw("$dateExpr as bucket,
                COUNT(*) as total,
                COUNT(CASE WHEN viewer_user_id IS NOT NULL " .
                ($totalFollowerCount === 0 ? "AND FALSE" : "AND viewer_user_id IN (" . $followerIds->map(fn($id) => (int)$id)->implode(',') . ")") .
                " THEN 1 END) as followers")
            ->groupByRaw($dateExpr)
            ->orderBy('bucket')
            ->get();

        // Fill any missing days with zeros so the chart line is continuous.
        $period_days = max(1, $startDate->diffInDays($endDate) + 1);
        $byDay = $rawDaily->keyBy('bucket');
        $dailySeries = collect();
        for ($i = 0; $i < $period_days; $i++) {
            $d = $startDate->copy()->addDays($i)->toDateString();
            $row = $byDay->get($d);
            $total = $row ? (int) $row->total : 0;
            $foll  = $row ? (int) $row->followers : 0;
            $dailySeries->push((object)[
                'd'           => $d,
                'followers'   => $foll,
                'nonfollowers'=> max(0, $total - $foll),
            ]);
        }

        // Top 10 most-engaged followers by clicks on this link in period.
        // Joined to users so we get name/avatar/email in one query.
        $topFollowers = $totalFollowerCount === 0 ? collect() : DB::table('link_clicks')
            ->join('users', 'users.id', '=', 'link_clicks.viewer_user_id')
            ->select(
                'users.id', 'users.name', 'users.email', 'users.avatar',
                DB::raw('COUNT(*) as click_count'),
                DB::raw('COUNT(CASE WHEN link_clicks.block_id IS NOT NULL THEN 1 END) as block_click_count'),
                DB::raw('MIN(link_clicks.clicked_at) as first_seen'),
                DB::raw('MAX(link_clicks.clicked_at) as last_seen')
            )
            ->where('link_clicks.link_id', $link->id)
            ->where('link_clicks.is_bot', false)
            ->whereBetween('link_clicks.clicked_at', [$startDate, $endDate])
            ->whereIn('link_clicks.viewer_user_id', $followerIds)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar')
            ->orderByDesc('click_count')
            ->limit(10)
            ->get();

        // 4-week cohort retention: of the unique visitors (by IP) seen in
        // week 1 of a fixed 28-day window ending today, what % returned in
        // weeks 2/3/4? Computed per cohort (follower vs non-follower) so
        // creators can compare how "sticky" each audience is.
        $retentionEnd   = now();
        $retentionStart = $retentionEnd->copy()->subDays(28)->startOfDay();
        $retentionRows = $link->clicks()
            ->whereBetween('clicked_at', [$retentionStart, $retentionEnd])
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, viewer_user_id, ' .
                "FLOOR(EXTRACT(EPOCH FROM (clicked_at - ?::timestamp)) / 604800) as week_idx",
                [$retentionStart->toDateTimeString()])
            ->get();

        $followerIdSet = $followerIds->flip();
        $cohortVisitors = ['followers' => [[], [], [], []], 'nonfollowers' => [[], [], [], []]];
        foreach ($retentionRows as $r) {
            $w = (int) $r->week_idx;
            if ($w < 0 || $w > 3) continue;
            $isFollower = $r->viewer_user_id !== null && $followerIdSet->has($r->viewer_user_id);
            $bucket = $isFollower ? 'followers' : 'nonfollowers';
            $cohortVisitors[$bucket][$w][$r->ip_address] = true;
        }

        $retentionSeries = [];
        foreach (['followers', 'nonfollowers'] as $cohort) {
            $week1 = $cohortVisitors[$cohort][0];
            $week1Count = count($week1);
            $row = ['cohort' => $cohort, 'week1_count' => $week1Count, 'pct' => [100.0, 0.0, 0.0, 0.0]];
            if ($week1Count > 0) {
                for ($w = 1; $w <= 3; $w++) {
                    $returners = count(array_intersect_key($cohortVisitors[$cohort][$w], $week1));
                    $row['pct'][$w] = round(($returners / $week1Count) * 100, 1);
                }
            }
            $retentionSeries[$cohort] = $row;
        }

        return view('user.links.followers', compact(
            'link', 'period', 'groupBy', 'startDate', 'endDate',
            'totalFollowerCount', 'uniqueVisitors', 'uniqueFollowerVisitors',
            'followerVisitorPct', 'totalClicks', 'followerClicks',
            'nonFollowerClicks', 'dailySeries', 'topFollowers',
            'retentionSeries', 'retentionStart', 'retentionEnd'
        ));
    }

    /**
     * Per-block analytics drill-down (JSON) for the biolink analytics page.
     * Powers the modal that opens when a creator clicks a row in the block
     * stats table — clicks/day with range picker, top referrers, device
     * split, and the visitor-type breakdown (anonymous / registered /
     * follower / subscriber). Shares the aggregator with the mobile API
     * endpoint so both surfaces show identical numbers.
     */
    public function blockAnalytics(Request $request, Link $link, int $blockId)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();

        return response()->json([
            'data' => [
                'analytics' => BlockAnalyticsAggregator::aggregate($link, $blockId, $from, $to),
            ],
        ]);
    }

    /**
     * CSV export of EVERY follower (not just top 10) who clicked this link
     * in the selected period. Streams rows via chunked DB iteration so very
     * large follower lists don't load into memory all at once.
     */
    public function followersExport(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        if (!workspace_owner()?->getPlanFeature('analytics_export', true)) {
            return back()->with('error', 'Exporting stats is a paid feature. Upgrade your plan to download CSV exports.');
        }

        [$startDate, $endDate, $period] = $this->resolveAnalyticsRange($request);

        $followerIds = Follow::where('creator_id', $link->user_id)->pluck('follower_id');

        $query = DB::table('link_clicks')
            ->join('users', 'users.id', '=', 'link_clicks.viewer_user_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as click_count'),
                DB::raw('COUNT(CASE WHEN link_clicks.block_id IS NOT NULL THEN 1 END) as block_click_count'),
                DB::raw('MIN(link_clicks.clicked_at) as first_seen'),
                DB::raw('MAX(link_clicks.clicked_at) as last_seen')
            )
            ->where('link_clicks.link_id', $link->id)
            ->where('link_clicks.is_bot', false)
            ->whereBetween('link_clicks.clicked_at', [$startDate, $endDate])
            ->whereIn('link_clicks.viewer_user_id', $followerIds)
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('click_count')
            ->orderBy('users.id');

        $filename = sprintf(
            'followers-%s-%s-to-%s.csv',
            $link->alias ?: $link->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // Defend against CSV formula injection when the file is opened in a
        // spreadsheet — prefix any cell that starts with =, +, -, @ with a
        // single quote so the spreadsheet treats it as text.
        $safe = function ($value) {
            $s = (string) $value;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
                return "'" . $s;
            }
            return $s;
        };

        // Sensitive action — record the export attempt before streaming.
        // We log at intent-time (not on stream completion) so an aborted
        // download still leaves a trail of who tried.
        if (app()->bound('current_workspace')) {
            app(\App\Modules\User\Services\SensitiveActionLogger::class)->record(
                app('current_workspace'),
                \App\Modules\User\Services\SensitiveActionLogger::ACTION_FOLLOWERS_EXPORTED,
                'link',
                $link->id,
                $link->title ?: ($link->alias ?: ('Link #'.$link->id)),
                [
                    'follower_count' => $followerIds->count(),
                    'range_start'    => $startDate->toDateString(),
                    'range_end'      => $endDate->toDateString(),
                    'filename'       => $filename,
                ],
            );
        }

        return response()->streamDownload(function () use ($query, $followerIds, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'total clicks', 'block clicks', 'first seen', 'last seen']);

            if ($followerIds->isEmpty()) {
                fclose($out);
                return;
            }

            $query->chunk(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $safe($r->name ?: 'Anonymous'),
                        $safe($r->email),
                        (int) $r->click_count,
                        (int) $r->block_click_count,
                        $r->first_seen,
                        $r->last_seen,
                    ]);
                }
                if (function_exists('flush')) { flush(); }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Drill-down: visit history for ONE follower on THIS link. Reached by
     * clicking a row in the Top Followers table on the Followers tab.
     */
    public function followerHistory(Request $request, Link $link, User $follower)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // Confirm this user is actually a follower of the link's owner so
        // the page can't be used as a generic per-user click viewer.
        $isFollower = Follow::where('creator_id', $link->user_id)
            ->where('follower_id', $follower->id)
            ->exists();
        abort_unless($isFollower, 404);

        [$startDate, $endDate, $period] = $this->resolveAnalyticsRange($request);

        $visits = $link->clicks()
            ->where('viewer_user_id', $follower->id)
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->orderByDesc('clicked_at')
            ->paginate(50)
            ->withQueryString();

        $totalVisits = $link->clicks()
            ->where('viewer_user_id', $follower->id)
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->count();

        $blockTypes = \App\Modules\User\Models\BiolinkBlock::TYPES;

        return view('user.links.follower-history', compact(
            'link', 'follower', 'visits', 'totalVisits',
            'period', 'startDate', 'endDate', 'blockTypes'
        ));
    }

    public function heatmap(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        // True period total (never truncated by the points cap below).
        $totalGeoClicks = (int) $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        // Aggregate by exact (lat, lng) so each point is a real city marker.
        // Cross-DB: only uses GROUP BY + COUNT + MAX. Capped at the busiest
        // 5000 hotspots to bound response size.
        $rows = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
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
        // lat/lng (older clicks, or ones the backfill couldn't resolve).
        // Group by (city, country_code) and resolve each group to a coarser
        // pin via the offline CityLookupService so they still show up on
        // the map instead of being silently dropped.
        $cityRows = $link->clicks()
            ->whereBetween('clicked_at', [$startDate, $endDate])
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
            // Keep busiest hotspots first after merging both passes.
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

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
            'points'   => $points,
            'meta'     => [
                'max_weight'    => $maxWeight,
                'point_count'   => count($features),
                'total_clicks'  => $totalGeoClicks,
                'shown_clicks'  => $shownClicks,
                'period_start'  => $startDate->toIso8601String(),
                'period_end'    => $endDate->toIso8601String(),
            ],
        ]);
    }

    public function heatmapLive(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // "Live" window: last 5 minutes of clicks. Short enough to feel real-time,
        // long enough to keep a few pulses on screen between 10s polls.
        $windowStart = now()->subMinutes(5);

        $rows = $link->clicks()
            ->where('clicked_at', '>=', $windowStart)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('clicked_at')
            ->limit(200)
            ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'channel', 'clicked_at', 'ip_address']);

        $points = $this->formatLivePoints($rows);

        $uniqueVisitors = $rows->pluck('ip_address')->filter()->unique()->count();

        return response()->json([
            'points' => $points,
            'meta'   => [
                'count'           => count($points),
                'unique_visitors' => $uniqueVisitors,
                'window_seconds'  => 300,
                'server_time'     => now()->toIso8601String(),
                'server_ts'       => now()->getTimestamp(),
            ],
        ]);
    }

    /**
     * Server-Sent Events stream of recent visitors.
     *
     * The browser opens this once and receives new clicks as soon as the
     * server notices them — no client polling. Works for any Laravel setup
     * because it doesn't require Redis/Reverb/pub-sub: the server itself
     * tails the clicks table at a short interval and flushes each new row
     * out over the held connection.
     *
     * The connection is capped at ~55s so PHP-FPM/proxies don't kill it
     * mid-flight; EventSource reconnects automatically and the `lastId`
     * cursor (remembered client-side and echoed back in each payload)
     * prevents duplicate pins across reconnects.
     */
    public function heatmapLiveStream(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // Every query in this stream goes through `$link->clicks()`, which
        // applies the LinkClick `is_bot = false` global scope. Bot/scraper
        // hits therefore never appear in the live pulse, the unique-visitor
        // counter, or the SSE event payloads — only humans do.
        $lastId = (int) $request->query('lastId', 0);
        $sinceTs = $request->query('since');
        // When the client has no cursor yet, seed from the existing 5-minute
        // window so the first batch of events matches what `heatmapLive()`
        // would have returned — keeps the "X live visitors" pill accurate
        // the moment the stream opens.
        $windowStart = $sinceTs
            ? \Carbon\Carbon::createFromTimestamp((int) $sinceTs)
            : now()->subMinutes(5);

        $maxDurationSec = 55;        // cap before forcing client reconnect
        $pollIntervalMs = 1000;      // server-side DB tail cadence
        $heartbeatEverySec = 15;     // keep proxies / browsers from timing out
        // `?once=1` emits just the initial snapshot and exits — used by
        // automated tests so they don't have to wait out the tail loop.
        $onceOnly = (bool) $request->query('once', false);

        return response()->stream(function () use ($link, $lastId, $windowStart, $maxDurationSec, $pollIntervalMs, $heartbeatEverySec, $onceOnly) {
            @ini_set('zlib.output_compression', '0');

            $emit = function (string $event, array $payload) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();
            };

            // Initial snapshot: same shape as the polling endpoint so the
            // client can reuse its existing rendering path.
            $initialRows = $link->clicks()
                ->where('clicked_at', '>=', $windowStart)
                ->when($lastId > 0, fn($q) => $q->where('id', '>', $lastId))
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'channel', 'clicked_at', 'ip_address']);

            foreach ($initialRows as $r) {
                if ((int) $r->id > $lastId) $lastId = (int) $r->id;
            }

            $uniqueVisitors = $link->clicks()
                ->where('clicked_at', '>=', now()->subMinutes(5))
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address');

            $emit('snapshot', [
                'points' => $this->formatLivePoints($initialRows),
                'meta'   => [
                    'count'           => $initialRows->count(),
                    'unique_visitors' => $uniqueVisitors,
                    'window_seconds'  => 300,
                    'server_ts'       => now()->getTimestamp(),
                    'last_id'         => $lastId,
                ],
            ]);

            if ($onceOnly) {
                $emit('bye', ['last_id' => $lastId]);
                return;
            }

            $deadline = microtime(true) + $maxDurationSec;
            $lastHeartbeat = microtime(true);

            while (microtime(true) < $deadline) {
                if (connection_aborted()) break;

                $newRows = $link->clicks()
                    ->where('id', '>', $lastId)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->orderBy('id')
                    ->limit(100)
                    ->get(['id', 'latitude', 'longitude', 'city', 'country_code', 'channel', 'clicked_at', 'ip_address']);

                if ($newRows->isNotEmpty()) {
                    foreach ($newRows as $r) {
                        if ((int) $r->id > $lastId) $lastId = (int) $r->id;
                    }
                    $unique = $link->clicks()
                        ->where('clicked_at', '>=', now()->subMinutes(5))
                        ->whereNotNull('ip_address')
                        ->distinct('ip_address')
                        ->count('ip_address');
                    $emit('clicks', [
                        'points' => $this->formatLivePoints($newRows),
                        'meta'   => [
                            'unique_visitors' => $unique,
                            'server_ts'       => now()->getTimestamp(),
                            'last_id'         => $lastId,
                        ],
                    ]);
                    $lastHeartbeat = microtime(true);
                } elseif (microtime(true) - $lastHeartbeat >= $heartbeatEverySec) {
                    // Comment frame = SSE "ping" — keeps intermediaries from
                    // closing an idle connection but isn't delivered as an event.
                    echo ": ping\n\n";
                    @ob_flush();
                    @flush();
                    $lastHeartbeat = microtime(true);
                }

                usleep($pollIntervalMs * 1000);
            }

            $emit('bye', ['last_id' => $lastId]);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Shared point formatter for the polling and SSE live endpoints so both
     * return identical shapes to the browser.
     */
    /**
     * Validate + clean the smart_rules JSON submitted from the editor.
     * Drops anything malformed instead of erroring — the form already shows
     * client-side validation, so by the time it reaches us we just want to
     * make sure nothing weird gets persisted to settings.
     */
    /**
     * Parse the shared "Protection & Scheduling" partial inputs into a
     * persistable form. Returns:
     *   [
     *     'settings'   => keys to merge into Link::$settings (replaces
     *                     timezone / start_at / max_clicks / expire_on_first_click /
     *                     expiry_url / active_window / country_blocklist),
     *     'expires_at' => Carbon|null  — value for the links.expires_at column,
     *   ]
     *
     * Caller is responsible for applying these (so each link-type controller
     * can still merge its own settings around them). datetime-local inputs
     * are interpreted as wall-clock time in the chosen timezone, then
     * converted to UTC for storage.
     */
    public static function applyProtectionScheduling(Request $request): array
    {
        $settings = [];

        // ---- Timezone (validated against PHP's known list) ----------------
        $tz = trim((string) $request->input('tz', 'UTC'));
        if ($tz === '' || !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = 'UTC';
        }
        $settings['timezone'] = $tz;

        // ---- Goes-live (start_at) — stored in UTC ISO ---------------------
        $startRaw = trim((string) $request->input('start_at', ''));
        if ($startRaw !== '') {
            try {
                $settings['start_at'] = \Carbon\Carbon::parse($startRaw, $tz)->utc()->toIso8601String();
            } catch (\Throwable $e) { /* drop silently */ }
        }

        // ---- Expiry rule -------------------------------------------------
        $expMode    = $request->input('_exp_mode', 'none');
        $expiresCol = null;
        if ($expMode === 'date') {
            $expRaw = trim((string) $request->input('expires_at', ''));
            if ($expRaw !== '') {
                try {
                    $expiresCol = \Carbon\Carbon::parse($expRaw, $tz)->utc();
                } catch (\Throwable $e) { /* leave null */ }
            }
        } elseif ($expMode === 'first_click') {
            $settings['expire_on_first_click'] = true;
        } elseif ($expMode === 'clicks') {
            // Backward-compat: legacy "expires after N clicks" mode now maps
            // onto the independent click-limit field below.
        }

        // ---- Click limit (independent of expiry rule) --------------------
        // The shared partial posts an explicit on/off flag plus the cap.
        // Older callers without the flag still work: a positive max_clicks
        // is treated as "enabled".
        $clicksFlag = $request->input('click_limit_enabled', null);
        $maxClicks  = (int) $request->input('max_clicks', 0);
        $limitOn    = $clicksFlag !== null
            ? (bool) $request->boolean('click_limit_enabled')
            : ($maxClicks > 0);
        if ($limitOn && $maxClicks > 0) {
            $settings['max_clicks'] = $maxClicks;
        }

        $expiryUrl = trim((string) $request->input('expiry_url', ''));
        if ($expiryUrl !== '' && filter_var($expiryUrl, FILTER_VALIDATE_URL)) {
            // Strict scheme check — Link::getExpiryRedirectUrl feeds this into
            // redirect()->away(), so allowing javascript:/file:/etc. would be a
            // hole. Mirrors the safety constraint already on smart_rules URLs.
            $scheme = strtolower((string) parse_url($expiryUrl, PHP_URL_SCHEME));
            if ($scheme === 'http' || $scheme === 'https') {
                $settings['expiry_url'] = $expiryUrl;
            }
        }

        // ---- Daily active window (one or more time slots per day) --------
        if ($request->boolean('active_window_enabled')) {
            $starts = (array) $request->input('active_window_starts', []);
            $ends   = (array) $request->input('active_window_ends',   []);
            // Backward-compat with the old single-window field names.
            if (empty($starts) && $request->filled('active_window_start')) {
                $starts = [$request->input('active_window_start')];
                $ends   = [$request->input('active_window_end')];
            }
            $slots = [];
            foreach ($starts as $i => $s) {
                $sT = self::sanitizeTimeOfDay($s);
                $eT = self::sanitizeTimeOfDay($ends[$i] ?? null);
                if ($sT && $eT) $slots[] = ['start' => $sT, 'end' => $eT];
            }
            $days = array_values(array_intersect(
                ['mon','tue','wed','thu','fri','sat','sun'],
                (array) $request->input('active_window_days', [])
            ));
            if (!empty($slots)) {
                $settings['active_window'] = [
                    'enabled' => true,
                    'days'    => $days ?: ['mon','tue','wed','thu','fri','sat','sun'],
                    'slots'   => $slots,
                ];
            }
        }

        // ---- Banned countries (ISO 2-letter, uppercased) -----------------
        $blocklistRaw = (string) $request->input('country_blocklist', '');
        $codes = array_values(array_filter(array_map(
            fn ($c) => strtoupper(preg_replace('/[^A-Za-z]/', '', $c)),
            explode(',', $blocklistRaw)
        ), fn ($c) => strlen($c) === 2));
        if (!empty($codes)) {
            $settings['country_blocklist'] = array_values(array_unique($codes));
        }

        return ['settings' => $settings, 'expires_at' => $expiresCol];
    }

    /**
     * Merge protection-scheduling settings into an existing settings array,
     * stripping the keys this feature owns so toggling them off actually
     * removes the constraint instead of leaving stale values behind.
     */
    public static function mergeProtectionScheduling(array $existing, array $fresh): array
    {
        $owned = ['timezone', 'start_at', 'max_clicks', 'expire_on_first_click',
                  'expiry_url', 'active_window', 'country_blocklist'];
        foreach ($owned as $k) unset($existing[$k]);
        return array_merge($existing, $fresh);
    }

    private static function sanitizeTimeOfDay($v): ?string
    {
        if (!is_string($v)) return null;
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }

    /**
     * Validation rules for every input the shared "Protection & Scheduling"
     * partial posts. Merge this into a controller's $request->validate([...])
     * call so out-of-range values are rejected on the way in instead of being
     * silently coerced inside applyProtectionScheduling().
     */
    public static function protectionSchedulingRules(): array
    {
        return [
            'tz'                       => 'nullable|string|max:64',
            '_exp_mode'                => 'nullable|in:none,date,clicks,first_click',
            'expires_at'               => 'nullable|date',
            'start_at'                 => 'nullable|date',
            'click_limit_enabled'      => 'nullable|boolean',
            'max_clicks'               => 'nullable|integer|min:0|max:1000000000',
            'expire_on_first_click'    => 'nullable|boolean',
            'expiry_url'               => 'nullable|url|max:2048',
            'active_window_enabled'    => 'nullable|boolean',
            'active_window_starts'     => 'nullable|array|max:24',
            'active_window_starts.*'   => 'nullable|string|max:5',
            'active_window_ends'       => 'nullable|array|max:24',
            'active_window_ends.*'     => 'nullable|string|max:5',
            'active_window_days'       => 'nullable|array|max:7',
            'active_window_days.*'     => 'nullable|in:mon,tue,wed,thu,fri,sat,sun',
            'country_blocklist'        => 'nullable|string|max:500',
        ];
    }

    /**
     * True when the request is asking to use the "expiry" protection feature
     * in any form — date-based, first-click, or the independent click cap.
     * Centralised so plan gates stay consistent across controllers.
     */
    public static function isExpiryRequested(Request $request): bool
    {
        $expMode = $request->input('_exp_mode', 'none');
        if ($expMode !== 'none' && $expMode !== '') return true;

        // Independent click-limit toggle. When the explicit flag is present
        // (current UI) it wins outright — false means the user actively
        // turned the limit off, even if a stale max_clicks value is still
        // posted from the hidden field. When the flag is absent (legacy
        // callers without the new partial), fall back to "treat any positive
        // max_clicks as enabled".
        if ($request->input('click_limit_enabled', null) !== null) {
            return $request->boolean('click_limit_enabled');
        }
        return (int) $request->input('max_clicks', 0) > 0;
    }

    public static function sanitizeSmartRules(?string $json): array
    {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];

        // Mirrors SmartRedirectResolver::safeUrl exactly so a URL that
        // passes save-time validation can never be silently rejected at
        // runtime — and vice versa, no malformed URL ever lands in storage.
        $isSafeUrl = function (?string $u): bool {
            if (!is_string($u) || $u === '' || strlen($u) > 2048) return false;
            $p = @parse_url($u);
            if (!$p || empty($p['host'])) return false;
            $s = strtolower($p['scheme'] ?? '');
            return $s === 'http' || $s === 'https';
        };

        $allowedDevices = ['mobile', 'tablet', 'desktop'];
        // Rules carry a stable 12-char id so click analytics can attribute
        // a click to the rule that fired even after the rule list is
        // reordered. Preserve any submitted id that looks safe; mint a new
        // one otherwise. Same pattern AB variants already use.
        $idOrMint = function ($v) {
            return is_string($v) && preg_match('/^[A-Za-z0-9]{8,32}$/', $v)
                ? $v
                : bin2hex(random_bytes(6));
        };
        $clean = [];
        foreach (array_slice($decoded, 0, 25) as $r) {
            if (!is_array($r) || empty($r['type'])) continue;
            $type = (string) $r['type'];
            $url  = isset($r['url']) ? trim((string) $r['url']) : '';
            $urlOk = $isSafeUrl($url);
            $rid   = $idOrMint($r['id'] ?? null);
            $label = isset($r['label']) && is_string($r['label']) ? mb_substr(trim($r['label']), 0, 80) : '';

            switch ($type) {
                case 'device':
                    $match = is_array($r['match'] ?? null) ? array_values(array_intersect($allowedDevices, array_map('strtolower', $r['match']))) : [];
                    if ($urlOk && !empty($match)) $clean[] = ['id' => $rid, 'type' => 'device', 'match' => $match, 'url' => $url, 'label' => $label];
                    break;
                case 'country':
                    $match = is_array($r['match'] ?? null) ? array_values(array_unique(array_filter(array_map(
                        fn($v) => preg_match('/^[A-Za-z]{2}$/', (string) $v) ? strtoupper($v) : null,
                        $r['match']
                    )))) : [];
                    if ($urlOk && !empty($match)) $clean[] = ['id' => $rid, 'type' => 'country', 'match' => $match, 'url' => $url, 'label' => $label];
                    break;
                case 'language':
                    $match = is_array($r['match'] ?? null) ? array_values(array_unique(array_filter(array_map(
                        fn($v) => preg_match('/^[A-Za-z]{2,3}$/', (string) $v) ? strtolower($v) : null,
                        $r['match']
                    )))) : [];
                    if ($urlOk && !empty($match)) $clean[] = ['id' => $rid, 'type' => 'language', 'match' => $match, 'url' => $url, 'label' => $label];
                    break;
                case 'time':
                    $from = (string) ($r['from'] ?? '');
                    $to   = (string) ($r['to']   ?? '');
                    $tz   = (string) ($r['tz']   ?? 'UTC');
                    $hhmm = '/^([01]\d|2[0-3]):[0-5]\d$/';
                    if ($urlOk && preg_match($hhmm, $from) && preg_match($hhmm, $to) && in_array($tz, timezone_identifiers_list(), true)) {
                        $clean[] = ['id' => $rid, 'type' => 'time', 'from' => $from, 'to' => $to, 'tz' => $tz, 'url' => $url, 'label' => $label];
                    }
                    break;
                case 'ab':
                    $variants = [];
                    foreach (array_slice((array) ($r['variants'] ?? []), 0, 10) as $v) {
                        if (!is_array($v)) continue;
                        $vu = isset($v['url']) ? trim((string) $v['url']) : '';
                        $vw = isset($v['weight']) ? max(0, (int) $v['weight']) : 1;
                        if (!$isSafeUrl($vu) || $vw <= 0) continue;
                        // Preserve the stable id submitted by the editor so
                        // existing AB cookies continue to point at the same
                        // variant after edits. Mint a fresh id only when the
                        // submitted one is missing or doesn't look like the
                        // 12-char alphanum the editor produces — defensive
                        // against tampering or hand-written JSON.
                        $vid = isset($v['id']) && is_string($v['id']) && preg_match('/^[A-Za-z0-9]{8,32}$/', $v['id'])
                            ? $v['id']
                            : bin2hex(random_bytes(6));
                        $variants[] = ['id' => $vid, 'url' => $vu, 'weight' => $vw];
                    }
                    // Reject ids that collide within the same rule (would
                    // make stickiness ambiguous). Rare but cheap to guard.
                    $seen = [];
                    foreach ($variants as &$v) {
                        if (isset($seen[$v['id']])) $v['id'] = bin2hex(random_bytes(6));
                        $seen[$v['id']] = true;
                    }
                    unset($v);
                    if (count($variants) >= 2) $clean[] = ['id' => $rid, 'type' => 'ab', 'variants' => $variants, 'label' => $label];
                    break;
            }
        }
        return $clean;
    }

    private function formatLivePoints($rows): array
    {
        $points = [];
        foreach ($rows as $r) {
            $channelKey = $r->channel ?: null;
            $points[] = [
                'id'           => (int) $r->id,
                'lat'          => (float) $r->latitude,
                'lng'          => (float) $r->longitude,
                'city'         => $r->city,
                'country_code' => $r->country_code,
                'channel'      => $channelKey,
                'channel_label' => $channelKey
                    ? \App\Modules\Common\Services\ChannelClassifier::labelFor($channelKey)
                    : null,
                'clicked_at'   => optional($r->clicked_at)->toIso8601String(),
                'ts'           => optional($r->clicked_at)->getTimestamp(),
            ];
        }
        return $points;
    }

    public function exportClicks(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        if (!workspace_owner()?->getPlanFeature('analytics_export', true)) {
            return back()->with('error', 'Exporting stats is a paid feature. Upgrade your plan to download CSV exports.');
        }
        [$startDate, $endDate] = $this->resolveAnalyticsRange($request);

        // CSV exports default to real-human traffic — bot/scraper hits are
        // hidden everywhere else (totals, uniques, live feeds), so creators
        // would otherwise see one number in the dashboard and a different
        // number in the spreadsheet. Operators that genuinely want raw
        // traffic (debugging, audits) can opt in with `?include_bots=1`.
        $includeBots = filter_var($request->query('include_bots'), FILTER_VALIDATE_BOOL);

        $suffix = $includeBots ? '-with-bots' : '';
        $filename = 'clicks-' . $link->alias . $suffix . '-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columns = ['Clicked At', 'Link Type', 'Link Type Slug', 'IP', 'Country', 'City', 'Channel', 'Channel Key', 'Browser', 'OS', 'Device', 'Language', 'Referrer', 'Block ID', 'Block Type', 'Block Type Slug', 'Destination URL', 'UTM Source', 'UTM Medium', 'UTM Campaign'];
        if ($includeBots) {
            $columns[] = 'Is Bot';
        }

        $linkTypeLabel = \App\Modules\User\Models\Link::typeLabel($link->type);
        $linkTypeSlug = (string) $link->type;

        return response()->stream(function () use ($link, $startDate, $endDate, $columns, $linkTypeLabel, $linkTypeSlug, $includeBots) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $columns);

            // Without `include_bots`, rely on the LinkClick global scope to
            // strip bot rows. With it, drop the scope and append the flag
            // column so reviewers can still tell humans from scrapers.
            $query = $includeBots
                ? \App\Modules\User\Models\LinkClick::withBots()->where('link_id', $link->id)
                : $link->clicks();

            $query
                ->whereBetween('clicked_at', [$startDate, $endDate])
                ->orderByDesc('clicked_at')
                ->chunk(500, function ($rows) use ($h, $linkTypeLabel, $linkTypeSlug, $includeBots) {
                    foreach ($rows as $r) {
                        $u = $r->utm_params ?? [];
                        $blockTypeSlug = (string) ($r->block_type ?? '');
                        $blockTypeLabel = $blockTypeSlug !== ''
                            ? (\App\Modules\User\Models\BiolinkBlock::TYPES[$blockTypeSlug]['label'] ?? ucfirst(str_replace('_', ' ', $blockTypeSlug)))
                            : '';
                        $row = [
                            optional($r->clicked_at)->format('Y-m-d H:i:s'),
                            $linkTypeLabel, $linkTypeSlug,
                            $r->ip_address, $r->country_code, $r->city,
                            $r->channel ? \App\Modules\Common\Services\ChannelClassifier::labelFor($r->channel) : '',
                            $r->channel ?? '',
                            $r->browser, $r->os, $r->device_type, $r->language,
                            $r->referrer, $r->block_id, $blockTypeLabel, $blockTypeSlug, $r->destination_url,
                            $u['utm_source'] ?? '', $u['utm_medium'] ?? '', $u['utm_campaign'] ?? '',
                        ];
                        if ($includeBots) {
                            $row[] = $r->is_bot ? 'yes' : 'no';
                        }
                        fputcsv($h, $row);
                    }
                });
            fclose($h);
        }, 200, $headers);
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // For biolinks, all edit controls live on the unified Appearance
        // settings page so users have a single, premium place to manage them.
        if ($link->isBiolinkFamily()) {
            return redirect()->route('user.links.settings.appearance', $link);
        }

        // VCF links have their own dedicated editor with the rich vCard
        // builder (avatar, multiple emails/phones/urls/addresses/socials).
        if ($link->type === 'vcf') {
            return redirect()->route('user.links.vcf.edit', $link);
        }

        // ICS (Event Invite) links have their own editor with date/time,
        // location, organizer, recurrence, and multi-schedule support.
        if ($link->type === 'ics') {
            return redirect()->route('user.links.ics.edit', $link);
        }

        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $pixels = workspace_owner()->pixels()->orderBy('name')->get();
        $domains = \App\Modules\User\Models\Domain::availableTo($request->user())->get();
        $link->load('pixels');

        // Detect a known mobile app for the destination URL so the edit form
        // can show "Opens in YouTube on mobile" etc.
        $detectedApp = $link->type === 'url' ? AppLinkResolver::resolve($link->long_url) : null;

        $defaultDomainId = $domains->firstWhere('is_primary', true)?->id;

        // Resume links can point at a specific named résumé version. Surface
        // every version the owner has so the edit form can offer a picker
        // (empty selection falls back to the default version).
        $resumeVersions = $link->type === Link::TYPE_RESUME
            ? workspace_owner()->resumes()->get()
            : collect();

        return view('user.links.edit', compact('link', 'projects', 'pixels', 'domains', 'detectedApp', 'defaultDomainId', 'resumeVersions'));
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $userId = workspace_owner_id();

        $validated = $request->validate([
            'long_url' => 'nullable|url|max:2048',
            'redirect_type' => 'nullable|in:301,302',
            'title' => 'nullable|string|max:255',
            'project_id' => "nullable|exists:projects,id,user_id,{$userId}",
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
            'is_active' => 'boolean',
            'is_password_protected' => 'boolean',
            'password' => 'nullable|string|min:3|max:100',
            'open_in_app' => 'nullable|boolean',
            'show_preview_page' => 'nullable|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'pixel_ids' => 'nullable|array',
            'pixel_ids.*' => "exists:pixels,id,user_id,{$userId}",
            'seo_image' => \App\Services\UploadPolicy::rule('link.seo_image', $request->user()),
            'favicon' => \App\Services\UploadPolicy::rule('link.favicon', $request->user()),
            'country_restrictions' => 'nullable|string|max:500',
            'device_targeting' => 'nullable|array',
            'device_targeting.*' => 'in:desktop,mobile,tablet',
            'smart_rules_json' => 'nullable|string|max:20000',
            'visibility' => 'nullable|in:public,registered,followers,subscribers',
            'resume_id' => "nullable|exists:resumes,id,user_id,{$userId}",
        ] + self::protectionSchedulingRules());

        // Resume version pick. Only meaningful for resume links: the owner
        // chooses which named résumé version the short link resolves to.
        // An empty selection clears resume_id so the public page falls back
        // to the owner's default version. For any non-resume link, strip the
        // field entirely so it can never be stamped onto the wrong type.
        if ($link->type === Link::TYPE_RESUME) {
            $validated['resume_id'] = $validated['resume_id'] ?? null;
        } else {
            unset($validated['resume_id']);
        }

        // Per-link advanced setting gates (update path). The protection /
        // scheduling fields are processed later by applyProtectionScheduling
        // — gate them here so a downgraded user cannot bypass via update.
        $owner = workspace_owner();
        $expiryRequested = self::isExpiryRequested($request);
        $activeWindowRequested = $request->boolean('active_window_enabled');
        $countryBlocklistRequested = $request->filled('country_blocklist');
        $gateMap = [
            'password'         => !empty($validated['password']),
            'geo_targeting'    => !empty($validated['country_restrictions']) || $countryBlocklistRequested,
            'device_targeting' => !empty($validated['device_targeting']),
            'deep_link'        => array_key_exists('open_in_app', $validated) && $request->boolean('open_in_app'),
            'smart_rules'      => !empty($validated['smart_rules_json']),
            'expiry'           => $expiryRequested,
            'active_window'    => $activeWindowRequested,
        ];
        foreach ($gateMap as $setting => $requested) {
            if ($requested && !$owner->userCanUseLinkSetting($setting)) {
                return back()->withInput()->with('error', 'The "' . str_replace('_', ' ', $setting) . '" link setting isn\'t available on your current plan. Upgrade to enable it.');
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_protected'] = true;
        } else {
            unset($validated['password']);
            if (empty($validated['is_password_protected'])) {
                $validated['password'] = null;
                $validated['is_password_protected'] = false;
            }
        }

        try {
            if ($request->hasFile('seo_image')) {
                // OG / share image — photographic, downscale aggressively.
                $validated['seo_image'] = UserFile::createFromUpload($request->file('seo_image'), $request->user(), [
                    'compress_image' => true,
                    'max_width'      => 1200,
                    'max_height'     => 1200,
                    'quality'        => 85,
                ])->url;
            } else {
                unset($validated['seo_image']);
            }
            if ($request->hasFile('favicon')) {
                // Favicons are tiny / pixel-perfect — store as-is.
                $validated['favicon'] = UserFile::createFromUpload($request->file('favicon'), $request->user())->url;
            } else {
                unset($validated['favicon']);
            }
        } catch (\RuntimeException $e) {
            return $this->uploadError($request, $e->getMessage());
        }

        $settings = $link->settings ?? [];
        if (isset($validated['country_restrictions']) && $validated['country_restrictions']) {
            $settings['country_restrictions'] = array_map('trim', explode(',', $validated['country_restrictions']));
        } else {
            unset($settings['country_restrictions']);
        }
        if (!empty($validated['device_targeting'])) {
            $settings['device_targeting'] = $validated['device_targeting'];
        } else {
            unset($settings['device_targeting']);
        }

        // Protection & Scheduling — timezone, schedule, expiry rule, daily
        // active window, banned countries. The shared partial owns these
        // fields across all link types; we delegate parsing to a helper so
        // every editor produces identical persistence behavior.
        $ps = self::applyProtectionScheduling($request);
        $settings = self::mergeProtectionScheduling($settings, $ps['settings']);
        $validated['expires_at'] = $ps['expires_at'];

        // App-opener toggle (only meaningful for url-type links). The form
        // always submits a hidden `open_in_app=0` plus the checkbox so
        // unchecking really clears it.
        if ($link->type === 'url' && $request->has('open_in_app')) {
            // Respect the plan gate: a downgraded plan cannot enable the
            // deep-link feature even if the form posts open_in_app=1.
            $settings['open_in_app'] = $owner->userCanUseLinkSetting('deep_link')
                && $request->boolean('open_in_app');
        }

        // Engagement preview-page toggle (url/ics/vcf).
        if (in_array($link->type, ['url', 'ics', 'vcf'], true) && $request->has('show_preview_page')) {
            if ($request->boolean('show_preview_page')) {
                $settings['show_preview_page'] = true;
            } else {
                unset($settings['show_preview_page']);
            }
        }

        // Smart redirect rules — supported on every link type. Always present
        // in form, even empty, so unsetting is just "save with zero rules".
        if ($request->has('smart_rules_json')) {
            $rules = $this->sanitizeSmartRules($request->input('smart_rules_json'));
            if (!empty($rules)) {
                $settings['smart_rules'] = $rules;
            } else {
                unset($settings['smart_rules']);
            }
        }

        $validated['settings'] = !empty($settings) ? $settings : null;
        unset(
            $validated['country_restrictions'], $validated['device_targeting'],
            $validated['open_in_app'], $validated['show_preview_page'],
            $validated['smart_rules_json']
        );

        $pixelIds = $validated['pixel_ids'] ?? [];
        unset($validated['pixel_ids']);

        $link->update($validated);
        $link->pixels()->sync($pixelIds);

        \App\Modules\User\Services\WorkspaceActivityRecorder::record(
            null, 'link.update', 'link', $link->id,
            $link->title ?: $link->alias ?: $link->long_url,
            route('user.links.show', $link),
        );
        return redirect()->route('user.links.show', $link)
            ->with('success', 'Link updated successfully.');
    }

    /**
     * Splash page settings — an intermediate "transition" page that visitors
     * see before reaching the destination of ANY link type. Stored in the
     * link's settings JSON under the "splash" key (no migration needed).
     */
    public function splashSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        $link->load('splashPage');
        $splashPages = workspace_owner()->splashPages()->orderBy('name')->get(['id', 'name', 'title']);
        return view('user.links.settings.splash', compact('link', 'splashPages'));
    }

    public function updateSplash(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        $userId = workspace_owner_id();
        $validated = $request->validate([
            'splash_enabled'  => 'sometimes|boolean',
            'splash_page_id'  => ['nullable', \Illuminate\Validation\Rule::exists('splash_pages', 'id')->where('user_id', $userId)],
        ]);
        $link->splash_enabled = $request->boolean('splash_enabled');
        $link->splash_page_id = $validated['splash_page_id'] ?? null;
        if ($link->splash_enabled && !$link->splash_page_id) {
            $link->splash_enabled = false;
        }
        $link->save();
        return redirect()->route('user.links.splash', $link)
            ->with('success', 'Splash settings saved.');
    }

    public function destroy(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $linkId    = $link->id;
        $linkLabel = $link->title ?: ($link->alias ?: $link->long_url ?: ('Link #'.$link->id));
        $alias     = $link->alias;

        // If this is a "Keep in sync" event invite that was pushed to a
        // connected calendar, remove the upstream copy too so the guest's
        // calendar reflects the cancellation.
        try {
            $s = (array) ($link->settings ?? []);
            if ($link->type === 'ics'
                && ($s['calendar_sync_mode'] ?? 'off') === 'keep_in_sync'
                && !empty($s['push_calendar_account_id'])) {
                $account = \App\Modules\User\Models\CalendarAccount::where('id', $s['push_calendar_account_id'])
                    ->where('user_id', $link->user_id)->first();
                if ($account) {
                    app(\App\Modules\User\Services\Calendar\CalendarSyncService::class)
                        ->deletePushedLink($account, $link);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Calendar push-delete on link delete failed', [
                'link' => $linkId, 'err' => $e->getMessage(),
            ]);
        }

        $link->delete();

        \App\Modules\User\Services\WorkspaceActivityRecorder::record(
            null, 'link.delete', 'link', $linkId, $linkLabel,
            route('user.links.index'),
        );

        // Sensitive action — append to the workspace audit ledger and
        // (subject to owner prefs) email the workspace owner.
        if (app()->bound('current_workspace')) {
            app(\App\Modules\User\Services\SensitiveActionLogger::class)->record(
                app('current_workspace'),
                \App\Modules\User\Services\SensitiveActionLogger::ACTION_LINK_DELETED,
                'link',
                $linkId,
                $linkLabel,
                ['alias' => $alias],
            );
        }

        return redirect()->route('user.links.index')
            ->with('success', 'Link deleted successfully.');
    }

    /**
     * Move a single link to another workspace the signed-in user owns.
     *
     * Only the workspace owner can move resources between their workspaces
     * (members must not be able to ferry an owner's data into a workspace
     * they happen to belong to). Both the source link and the target
     * workspace must be owned by the signed-in user. Per-link plan limits
     * for the destination owner are not re-checked here because the
     * destination owner is the same user — total link count is unchanged.
     */
    public function move(Request $request, Link $link)
    {
        $user = $request->user();
        abort_if((int) $link->user_id !== $user->id, 403,
            'Only the workspace owner can move links.');

        $data = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);
        $target = Workspace::findOrFail($data['workspace_id']);
        abort_if((int) $target->owner_user_id !== $user->id, 403,
            'You can only move links into a workspace you own.');

        if ((int) $link->workspace_id === (int) $target->id) {
            return back()->with('info', 'That link is already in this workspace.');
        }

        $link->forceFill(['workspace_id' => $target->id])->save();

        return back()->with('success', "Moved to '{$target->name}'.");
    }

    /**
     * Bulk-move many links to another workspace in one shot. Filters down
     * to the rows the signed-in user owns so a tampered POST cannot move
     * someone else's links.
     */
    public function moveBulk(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'link_ids'     => 'required|array|min:1',
            'link_ids.*'   => 'integer',
        ]);
        $target = Workspace::findOrFail($data['workspace_id']);
        abort_if((int) $target->owner_user_id !== $user->id, 403,
            'You can only move links into a workspace you own.');

        $moved = Link::whereIn('id', $data['link_ids'])
            ->where('user_id', $user->id)
            ->where('workspace_id', '!=', $target->id)
            ->update(['workspace_id' => $target->id]);

        if ($moved === 0) {
            return back()->with('info', 'Nothing to move.');
        }
        return back()->with('success',
            $moved . ' link' . ($moved === 1 ? '' : 's') . " moved to '{$target->name}'.");
    }

    /**
     * Duplicate a link — copies all attributes (including settings) but
     * gives the new link a fresh alias, "(Copy)" suffix on the title, and
     * resets engagement counters. For File / Bio / VCF / ICS links the
     * type-specific child rows are also cloned so the duplicate is
     * immediately usable.
     */
    public function duplicate(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // Plan limit: a duplicate counts as a new link.
        $maxLinks = (int) workspace_owner()->getPlanFeature('max_links', 0);
        if ($maxLinks > 0 && workspace_owner()->links()->count() >= $maxLinks) {
            return back()->with('error', 'You have reached your plan link limit. Upgrade to duplicate more links.');
        }

        $copy = \DB::transaction(function () use ($link) {
            $new = $link->replicate(['total_clicks', 'unique_clicks', 'created_at', 'updated_at']);
            $new->alias = Link::generateAlias();
            $new->title = trim(($link->title ?: $link->alias) . ' (Copy)');
            $new->is_active = $link->is_active;
            $new->total_clicks = 0;
            $new->unique_clicks = 0;
            $new->save();

            // Clone every type-specific child row so the duplicate is
            // immediately usable for any link type. Each one is a fresh
            // query against the source link to avoid relying on whichever
            // relations happened to be eager-loaded.
            if ($f = $link->fileLink()->first()) {
                $nf = $f->replicate(['link_id']);
                $nf->link_id = $new->id;
                $nf->save();
            }
            if ($i = $link->icsData()->first()) {
                $ni = $i->replicate(['link_id']);
                $ni->link_id = $new->id;
                $ni->save();
            }
            if ($v = $link->vcfData()->first()) {
                $nv = $v->replicate(['link_id']);
                $nv->link_id = $new->id;
                $nv->save();
            }
            foreach ($link->biolinkBlocks()->get() as $b) {
                $nb = $b->replicate(['link_id']);
                $nb->link_id = $new->id;
                $nb->save();
            }

            return $new;
        });

        return redirect()->route('user.links.edit', $copy)
            ->with('success', 'Link duplicated. You\'re now editing the copy.');
    }

    /**
     * Reset analytics data for a link. By default wipes ALL click + engagement
     * data for the link; if `?alias=` is supplied and valid, only click rows
     * belonging to that alias are wiped (engagement sessions have no alias
     * column so they are preserved in the alias-scoped case).
     */
    public function resetStats(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $aliasScope = $request->query('alias') ?: $request->input('alias');
        if ($aliasScope && !in_array($aliasScope, $link->getAllAliases(), true)) {
            $aliasScope = null;
        }

        if ($aliasScope) {
            $deleted = \DB::table('link_clicks')
                ->where('link_id', $link->id)
                ->where('alias', $aliasScope)
                ->delete();
            $msg = "Reset complete — removed {$deleted} click" . ($deleted === 1 ? '' : 's') . " for /{$aliasScope}.";
        } else {
            \DB::transaction(function () use ($link) {
                \DB::table('block_views')->where('link_id', $link->id)->delete();
                \DB::table('page_sessions')->where('link_id', $link->id)->delete();
                \DB::table('link_clicks')->where('link_id', $link->id)->delete();

                // Finalized-day analytics is served from the daily rollups
                // (AnalyticsRollupReader, used by the mobile API). Clearing only
                // raw rows would leave the rollups intact, so the API analytics
                // endpoint would keep returning pre-reset by_day/by_dimension
                // values — clear this link's rollup rows too for cross-surface
                // parity. (Alias-scoped resets above stay partial: rollups are
                // link/day aggregates and can't be alias-decomposed.)
                if (\Schema::hasTable('link_click_daily_dimensions')) {
                    \DB::table('link_click_daily_dimensions')->where('link_id', $link->id)->delete();
                }
                if (\Schema::hasTable('link_click_daily')) {
                    \DB::table('link_click_daily')->where('link_id', $link->id)->delete();
                }
            });
            $msg = 'All analytics data for this link has been reset.';
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->route('user.links.show', $link)->with('success', $msg);
    }


    public function toggleActive(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $link->update(['is_active' => !$link->is_active]);

        return back()->with('success', 'Link status updated.');
    }

    /**
     * One-click action dispatched from a Performance Coach insight.
     *
     * Each supported action type maps 1:1 to a recommendation rendered by
     * LinkPerformanceCoach::build(). Ownership of the link (and every block
     * referenced in the payload) is re-verified here — we never trust the
     * block ids sent by the client, even though the coach only surfaces
     * actions for the current user's own blocks.
     */
    public function coachAction(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $validated = $request->validate([
            'action_type' => 'required|string|in:deactivate_blocks,promote_block,enable_block',
            'block_id'    => 'nullable|integer',
            'block_ids'   => 'nullable|array|max:50',
            'block_ids.*' => 'integer',
        ]);

        $type = $validated['action_type'];

        // Scope every referenced block id to THIS link so a crafted request
        // can't mutate a block on another link the user does (or doesn't) own.
        $scopedBlocks = function (array $ids) use ($link) {
            return \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                ->whereIn('id', $ids)
                ->get();
        };

        $message = null;
        // Pre-change snapshot for the Undo control rendered on the next page
        // view. Each branch records the minimum state needed to invert the
        // mutation. Null => no undo offered (nothing actually changed).
        $undoData = null;

        if ($type === 'deactivate_blocks') {
            $ids = $validated['block_ids'] ?? [];
            if (empty($ids)) {
                return back()->with('error', 'No blocks specified.');
            }
            $blocks = $scopedBlocks($ids);
            if ($blocks->isEmpty()) {
                return back()->with('error', 'Those blocks no longer exist.');
            }
            // Only blocks that were actually active get flipped — those are
            // the ones Undo should re-enable.
            $changedIds = $blocks->where('is_active', true)->pluck('id')->all();
            if (!empty($changedIds)) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->whereIn('id', $changedIds)
                    ->update(['is_active' => false]);
                $undoData = ['ids' => array_values($changedIds)];
            }
            $n = count($changedIds);
            $message = $n === 1
                ? 'Hid 1 zero-click block.'
                : "Hid {$n} zero-click blocks.";
        } elseif ($type === 'enable_block') {
            $id = (int) ($validated['block_id'] ?? 0);
            if ($id <= 0) {
                return back()->with('error', 'No block specified.');
            }
            $block = $scopedBlocks([$id])->first();
            if (!$block) {
                return back()->with('error', 'That block no longer exists.');
            }
            if (!$block->is_active) {
                $block->update(['is_active' => true]);
                $undoData = ['id' => $block->id];
            }
            $message = 'Block enabled.';
        } elseif ($type === 'promote_block') {
            $id = (int) ($validated['block_id'] ?? 0);
            if ($id <= 0) {
                return back()->with('error', 'No block specified.');
            }
            $block = $scopedBlocks([$id])->first();
            if (!$block) {
                return back()->with('error', 'That block no longer exists.');
            }
            // Promotion is a reorder within the block's current parent scope
            // (top-level siblings, or a card's children). Assign the target
            // block sort_order=0, then renumber its siblings from 1 onward,
            // preserving their relative order.
            $siblingsQuery = \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id);
            if ($block->parent_id === null) {
                $siblingsQuery->whereNull('parent_id');
            } else {
                $siblingsQuery->where('parent_id', $block->parent_id);
            }
            $siblings = $siblingsQuery->orderBy('sort_order')->orderBy('id')->get();

            // Capture pre-reorder sort_order for every sibling so Undo can
            // restore the exact prior arrangement.
            $prevOrder = [];
            foreach ($siblings as $sib) {
                $prevOrder[(string) $sib->id] = (int) $sib->sort_order;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($siblings, $block) {
                $next = 1;
                foreach ($siblings as $sib) {
                    if ($sib->id === $block->id) {
                        $sib->update(['sort_order' => 0]);
                    } else {
                        $sib->update(['sort_order' => $next++]);
                    }
                }
            });

            $undoData = ['order' => $prevOrder];
            $message = 'Moved to the top of the page.';
        }

        $response = back()->with('success', $message ?? 'Done.');

        if ($undoData !== null) {
            // Sign the undo payload so the bounded, idempotent undo action
            // doesn't require a new DB table. The token embeds link/user and
            // an expiry so it can't be replayed later or on another link.
            $token = Crypt::encrypt([
                'link_id'    => $link->id,
                'user_id'    => workspace_owner_id(),
                'type'       => $type,
                'data'       => $undoData,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
            $response->with('coach_undo', $token);
        }

        return $response;
    }

    /**
     * Reverse the most recent one-click coach action. The inverse is derived
     * from a short-lived, encrypted token issued by coachAction(); no new
     * table is needed. Re-applying the same token is a no-op because the
     * inverse writes specific prior values (active=true / specific sort
     * orders) which are already in place after the first undo.
     */
    public function coachUndo(Request $request)
    {
        $validated = $request->validate([
            'undo_token' => 'required|string',
        ]);

        try {
            $payload = Crypt::decrypt($validated['undo_token']);
        } catch (\Throwable $e) {
            return back()->with('error', 'This undo link is no longer valid.');
        }

        if (!is_array($payload)
            || ($payload['user_id'] ?? null) !== workspace_owner_id()
            || empty($payload['link_id'])
            || empty($payload['type'])
        ) {
            abort(403);
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            return back()->with('error', 'The undo window has expired.');
        }

        $link = Link::where('user_id', workspace_owner_id())
            ->where('id', $payload['link_id'])
            ->first();
        if (!$link) {
            abort(404);
        }

        $type = $payload['type'];
        $data = $payload['data'] ?? [];
        $message = 'Change undone.';

        if ($type === 'deactivate_blocks') {
            $ids = array_values(array_filter(array_map('intval', $data['ids'] ?? [])));
            if (!empty($ids)) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->whereIn('id', $ids)
                    ->update(['is_active' => true]);
            }
            $message = count($ids) === 1
                ? 'Restored hidden block.'
                : 'Restored ' . count($ids) . ' hidden blocks.';
        } elseif ($type === 'enable_block') {
            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                    ->where('id', $id)
                    ->update(['is_active' => false]);
            }
            $message = 'Block disabled again.';
        } elseif ($type === 'promote_block') {
            $order = $data['order'] ?? [];
            if (is_array($order) && !empty($order)) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($link, $order) {
                    foreach ($order as $id => $sort) {
                        \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
                            ->where('id', (int) $id)
                            ->update(['sort_order' => (int) $sort]);
                    }
                });
            }
            $message = 'Restored previous block order.';
        } else {
            return back()->with('error', 'Unknown undo action.');
        }

        return redirect()->route('user.links.show', $link)->with('success', $message);
    }

    public function updateAlias(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        // Per-plan alias minimum (free/unconfigured = largest, paid tiers
        // step down). Editing the primary alias enforces the same floor as
        // creation and the live availability checker.
        $aliasLimits = workspace_owner()->getAliasLengthLimits();

        $validated = $request->validate([
            'alias' => [
                'required',
                'string',
                'min:' . $aliasLimits['min'],
                'max:' . $aliasLimits['max'],
                new \App\Modules\User\Rules\AliasFormat(),
                new \App\Modules\User\Rules\UniqueAliasCi($link->id),
                // Aliases must be globally unique across BOTH tables — also
                // reject if the value is already used as an extra alias on
                // any other link (an extra owned by THIS link is fine; we'll
                // demote it implicitly below). Matched case-insensitively so a
                // case-variant of an existing extra alias is rejected too.
                function ($attr, $value, $fail) use ($link) {
                    // Reserved top-level paths must never be claimed (mirror LinkAliasController).
                    $reserved = \App\Modules\User\Controllers\LinkAliasController::reservedAliases();
                    if (in_array(strtolower($value), $reserved, true)) {
                        $fail("'{$value}' is a reserved name and cannot be used.");
                        return;
                    }
                    $exists = \App\Modules\User\Models\LinkAlias::whereRaw('LOWER(alias) = ?', [mb_strtolower((string) $value)])
                        ->where('link_id', '!=', $link->id)
                        ->exists();
                    if ($exists) $fail('This alias is already taken. Please choose another.');
                },
                new \App\Modules\Admin\Rules\NotBannedName(),
            ],
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
        ]);

        // If the new primary value is currently an EXTRA alias on this same
        // link, free that row first so the unique constraint on link_aliases
        // is not violated when we later demote (handled by promote() flow);
        // here we simply delete the dup since it's about to live on links.alias.
        \App\Modules\User\Models\LinkAlias::where('link_id', $link->id)
            ->where('alias', $validated['alias'])
            ->delete();

        $update = ['alias' => $validated['alias']];
        if ($request->has('domain_id')) {
            $update['domain_id'] = $validated['domain_id'] ?: null;
        }
        $link->update($update);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'alias' => $validated['alias'], 'domain_id' => $link->domain_id]);
        }

        return back()->with('success', 'URL alias updated successfully.');
    }

    /**
     * Mint a fresh signed preview URL for the editor's device-preview iframe.
     *
     * The blade signs a 24h URL on first render. Long editing sessions outlast
     * that window, after which Laravel's signature middleware would 403 and the
     * iframe would show the default "Invalid signature" page. The editor calls
     * this endpoint shortly before expiry (and on demand after a banner click)
     * to swap in a fresh URL without a full page reload.
     */
    public function previewUrl(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        $expiresAt = now()->addHours(24);
        // Sign as a RELATIVE URL so the signature stays valid no matter
        // which platform host the iframe ends up loading it on (the host
        // baked into APP_URL is often not the host the browser is using —
        // e.g. on a Replit dev domain). The iframe resolves the relative
        // path against the parent page so it stays same-origin either way.
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'redirect.handle',
            $expiresAt,
            ['alias' => $link->alias, '_preview' => 1],
            false
        );

        return response()->json([
            'url'        => $url,
            'expires_at' => $expiresAt->getTimestamp(),
        ]);
    }

    /**
     * Hard ceiling on rows accepted in a single bulk-create submission.
     * Keeps synchronous processing fast and bounds DB pressure.
     */
    public const BULK_URL_MAX_ROWS = 500;

    /**
     * Step 1 — render the Bulk Create page (textarea + CSV upload + the
     * shared options that will apply to every row in the batch).
     */
    public function bulkCreateUrl(Request $request)
    {
        $owner = workspace_owner();
        return view('user.links.bulk-url', [
            'projects'    => $owner->projects()->orderBy('name')->get(),
            'pixels'      => $owner->pixels()->orderBy('name')->get(),
            'domains'     => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
            'aliasLimits' => $owner->getAliasLengthLimits(),
            'maxRows'     => self::BULK_URL_MAX_ROWS,
        ]);
    }

    /**
     * Step 2 — parse the input (paste or CSV), validate every row, and
     * render the editable preview table. Shared options are carried
     * forward as hidden fields so the user can fix/skip rows then submit
     * to bulkStoreUrl without losing their settings.
     */
    public function bulkPreviewUrl(Request $request)
    {
        [$shared, $sharedErrors] = $this->validateBulkSharedOptions($request);
        $rows = $this->parseBulkUrlInput($request);

        if (count($rows) > self::BULK_URL_MAX_ROWS) {
            return back()->withInput()->with('error', 'Too many rows. The bulk create limit is ' . self::BULK_URL_MAX_ROWS . ' per submission. You submitted ' . count($rows) . '.');
        }
        if (empty($rows)) {
            return back()->withInput()->with('error', 'No URLs found. Paste at least one URL or upload a CSV with a long_url column.');
        }
        if (!empty($sharedErrors)) {
            return back()->withInput()->with('error', $sharedErrors[0]);
        }

        $owner = workspace_owner();
        $validated = $this->validateBulkRows($rows, $owner);
        $validCount = collect($validated)->where('errors', [])->count();

        // Surface plan limits at the preview step so the user sees
        // "this batch is too large for your plan" before clicking
        // Create. Same checks run again on submit as a safety net.
        $planFeatures = $owner->plan?->features ?? [];
        $maxLinks = $planFeatures['max_links'] ?? 5;
        if ($maxLinks !== -1 && $validCount > 0) {
            $current = $owner->links()->count();
            if (($current + $validCount) > $maxLinks) {
                $remaining = max(0, $maxLinks - $current);
                session()->flash('error', "This batch would exceed your plan's link limit ({$maxLinks}). You can create at most {$remaining} more link(s) — check rows you want to skip, or upgrade your plan.");
            }
        }
        if (!empty($shared['password']) && !$owner->userCanUseLinkSetting('password')) {
            session()->flash('error', 'Password protection isn\'t available on your current plan — clear the shared password to proceed.');
        } elseif (!empty($shared['expires_at']) && !$owner->userCanUseLinkSetting('expiry')) {
            session()->flash('error', 'Link expiry isn\'t available on your current plan — clear the shared expiry to proceed.');
        }

        return view('user.links.bulk-url-preview', [
            'rows'        => $validated,
            'shared'      => $shared,
            'projects'    => $owner->projects()->orderBy('name')->get(),
            'pixels'      => $owner->pixels()->orderBy('name')->get(),
            'domains'     => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
            'aliasLimits' => $owner->getAliasLengthLimits(),
            'maxRows'     => self::BULK_URL_MAX_ROWS,
            'validCount'  => $validCount,
        ]);
    }

    /**
     * Step 3 — re-validate every row server-side, enforce plan limits on
     * the whole batch, then create the links inside one DB transaction
     * and dispatch the per-link `link_published` feed event. Renders the
     * results screen with copy buttons and a JSON-embedded result set
     * the page uses to power the "Download CSV" button.
     */
    public function bulkStoreUrl(Request $request)
    {
        $owner = workspace_owner();
        [$shared, $sharedErrors] = $this->validateBulkSharedOptions($request);

        // Reconstruct rows from the preview form. Each row carries
        // long_url + optional alias/title; rows with skip=1 are dropped.
        $raw = (array) $request->input('rows', []);
        $rows = [];
        foreach ($raw as $i => $r) {
            if (!is_array($r)) continue;
            if (!empty($r['skip'])) continue;
            $rows[] = [
                'long_url' => trim((string) ($r['long_url'] ?? '')),
                'alias'    => trim((string) ($r['alias']    ?? '')),
                'title'    => trim((string) ($r['title']    ?? '')),
            ];
        }

        // Helper: re-render the preview screen with the user's edits
        // intact and an error banner. Avoids `back()` redirects to the
        // POST-only preview URL (which would 405) and keeps every plan
        // / validation failure on a renderable page.
        $renderPreview = function (array $rows, string $error) use ($request, $shared) {
            if (empty($rows)) {
                return redirect()->route('user.links.url.bulk')->with('error', $error);
            }
            $validated = $this->validateBulkRows($rows, workspace_owner());
            $validCount = collect($validated)->where('errors', [])->count();
            return response()->view('user.links.bulk-url-preview', [
                'rows'        => $validated,
                'shared'      => $shared,
                'projects'    => workspace_owner()->projects()->orderBy('name')->get(),
                'pixels'      => workspace_owner()->pixels()->orderBy('name')->get(),
                'domains'     => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
                'aliasLimits' => workspace_owner()->getAliasLengthLimits(),
                'maxRows'     => self::BULK_URL_MAX_ROWS,
                'validCount'  => $validCount,
            ])->withHeaders([])->setStatusCode(422)
              ->header('X-Bulk-Error', '1') // marker (no-op for browsers; useful in tests)
            ;
        };
        // Flash the error so the preview view's `session('error')`
        // banner shows it. Wrap the view response so flashing works.
        $previewWithError = function (string $error) use ($renderPreview, &$rows, $request) {
            session()->flash('error', $error);
            return $renderPreview($rows, $error);
        };

        if (count($rows) > self::BULK_URL_MAX_ROWS) {
            return redirect()->route('user.links.url.bulk')
                ->with('error', 'Too many rows. The bulk create limit is ' . self::BULK_URL_MAX_ROWS . ' per submission.');
        }
        if (empty($rows)) {
            return redirect()->route('user.links.url.bulk')
                ->with('error', 'No rows to create. Paste URLs or upload a CSV first.');
        }
        if (!empty($sharedErrors)) {
            return $previewWithError($sharedErrors[0]);
        }

        $validated = $this->validateBulkRows($rows, $owner);
        $validRows = array_values(array_filter($validated, fn ($r) => empty($r['errors'])));

        if (empty($validRows)) {
            return $previewWithError('None of the rows are valid — fix the highlighted issues and try again.');
        }

        // Plan link-quota gate for the whole batch.
        $planFeatures = $owner->plan?->features ?? [];
        $maxLinks = $planFeatures['max_links'] ?? 5;
        if ($maxLinks !== -1) {
            $current = $owner->links()->count();
            if (($current + count($validRows)) > $maxLinks) {
                $remaining = max(0, $maxLinks - $current);
                return $previewWithError("This batch would exceed your plan's link limit ({$maxLinks}). You can create {$remaining} more link(s) — upgrade your plan to create the rest in bulk.");
            }
        }

        // Hash the shared password once. Plan-gate every paid shared
        // option so a downgraded plan can't bypass them via bulk.
        $sharedPasswordHash = null;
        if (!empty($shared['password'])) {
            if (!$owner->userCanUseLinkSetting('password')) {
                return $previewWithError('Password protection isn\'t available on your current plan.');
            }
            $sharedPasswordHash = Hash::make($shared['password']);
        }
        if (!empty($shared['expires_at']) && !$owner->userCanUseLinkSetting('expiry')) {
            return $previewWithError('Link expiry isn\'t available on your current plan.');
        }

        $defaultActive = ($shared['type'] ?? 'url') === 'url'
            ? $owner->userCanUseLinkSetting('deep_link')
            : false;

        $sharedSettings = [];
        if (!empty($shared['show_preview_page'])) $sharedSettings['show_preview_page'] = true;
        $sharedSettings['open_in_app'] = $defaultActive;

        $results = [];
        DB::transaction(function () use ($validRows, $shared, $sharedPasswordHash, $sharedSettings, $owner, &$results) {
            foreach ($validRows as $row) {
                $alias = $row['final_alias'];
                $attrs = [
                    'user_id'              => $owner->id,
                    'project_id'           => $shared['project_id'] ?: null,
                    'domain_id'            => $shared['domain_id'] ?: null,
                    'type'                 => 'url',
                    'alias'                => $alias,
                    'title'                => $row['title'] !== '' ? $row['title'] : null,
                    'long_url'             => $row['long_url'],
                    'redirect_type'        => $shared['redirect_type'] ?: 301,
                    'is_active'            => true,
                    'expires_at'           => $shared['expires_at'] ?: null,
                    'is_password_protected'=> $sharedPasswordHash !== null,
                    'password'             => $sharedPasswordHash,
                    'seo_title'            => $shared['seo_title'] ?: null,
                    'seo_description'      => $shared['seo_description'] ?: null,
                    'utm_source'           => $shared['utm_source'] ?: null,
                    'utm_medium'           => $shared['utm_medium'] ?: null,
                    'utm_campaign'         => $shared['utm_campaign'] ?: null,
                    'utm_term'             => $shared['utm_term'] ?: null,
                    'utm_content'          => $shared['utm_content'] ?: null,
                    'settings'             => !empty($sharedSettings) ? $sharedSettings : null,
                ];
                $link = Link::create($attrs);
                if (!empty($shared['pixel_ids'])) {
                    $link->pixels()->sync($shared['pixel_ids']);
                }

                // Spec: dispatch link_published per link in the batch.
                // Bulk creates only url-type links, so we always emit
                // here regardless of the broader feed-type gate used
                // elsewhere — the task explicitly requires it.
                try {
                    $u = auth()->user();
                    \App\Modules\User\Models\FeedEvent::create([
                        'user_id'      => $link->user_id,
                        'type'         => 'link_published',
                        'subject_id'   => $link->id,
                        'subject_type' => Link::class,
                        'data'         => [
                            'title'          => $link->title,
                            'alias'          => $link->alias,
                            'creator_name'   => $u?->name,
                            'creator_avatar' => $u?->avatar,
                        ],
                        'occurred_at'  => now(),
                    ]);
                } catch (\Throwable $e) { \Log::warning('bulk feed event failed: ' . $e->getMessage()); }

                $results[] = [
                    'original_url' => $row['long_url'],
                    'short_url'    => $link->getShortUrl(),
                    'alias'        => $link->alias,
                    'status'       => 'created',
                    'error'        => '',
                ];
            }

            // One debounced followers ping for the whole batch (not one
            // per link, so a 500-row batch doesn't fire 500 pings).
            $u = auth()->user();
            if ($u && !empty($results)) {
                try {
                    \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced(
                        $u,
                        'published ' . count($results) . ' new link(s) in bulk'
                    );
                } catch (\Throwable $e) { \Log::warning('bulk followers ping failed: ' . $e->getMessage()); }
            }
        });

        // Append skipped rows so the CSV download is a complete record.
        foreach ($validated as $r) {
            if (!empty($r['errors'])) {
                $results[] = [
                    'original_url' => $r['long_url'],
                    'short_url'    => '',
                    'alias'        => $r['alias'],
                    'status'       => 'skipped',
                    'error'        => implode('; ', $r['errors']),
                ];
            }
        }

        return view('user.links.bulk-url-results', [
            'results' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'skipped' => count(array_filter($results, fn ($r) => $r['status'] !== 'created')),
        ]);
    }

    /**
     * Read the textarea + uploaded CSV from the request and return a
     * normalized list of `[long_url, alias, title]` rows.
     */
    private function parseBulkUrlInput(Request $request): array
    {
        $rows = [];

        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $handle = @fopen($file->getRealPath(), 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                if (is_array($header)) {
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
                    $iUrl   = array_search('long_url', $header, true);
                    if ($iUrl === false) $iUrl = array_search('url', $header, true);
                    if ($iUrl === false) $iUrl = array_search('destination', $header, true);
                    $iAlias = array_search('alias', $header, true);
                    $iTitle = array_search('title', $header, true);
                    while (($r = fgetcsv($handle)) !== false) {
                        if (count($r) === 1 && trim((string) $r[0]) === '') continue;
                        $rows[] = [
                            'long_url' => $iUrl   !== false ? trim((string) ($r[$iUrl]   ?? '')) : '',
                            'alias'    => $iAlias !== false ? trim((string) ($r[$iAlias] ?? '')) : '',
                            'title'    => $iTitle !== false ? trim((string) ($r[$iTitle] ?? '')) : '',
                        ];
                        if (count($rows) > self::BULK_URL_MAX_ROWS + 1) break;
                    }
                }
                fclose($handle);
            }
            return $rows;
        }

        // Re-submission from the preview screen — already structured.
        if ($request->has('rows')) {
            foreach ((array) $request->input('rows') as $r) {
                if (!is_array($r)) continue;
                if (!empty($r['skip'])) continue;
                $rows[] = [
                    'long_url' => trim((string) ($r['long_url'] ?? '')),
                    'alias'    => trim((string) ($r['alias']    ?? '')),
                    'title'    => trim((string) ($r['title']    ?? '')),
                ];
            }
            return $rows;
        }

        $text = (string) $request->input('urls_text', '');
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $rows[] = ['long_url' => $line, 'alias' => '', 'title' => ''];
        }
        return $rows;
    }

    /**
     * Per-row validation: URL format, alias format/length/banned/uniqueness
     * (against existing links AND duplicate aliases within the same batch).
     * Auto-generates an alias when the row left it blank.
     */
    private function validateBulkRows(array $rows, User $owner): array
    {
        $limits = $owner->getAliasLengthLimits();
        $aliasPattern = \App\Modules\User\Rules\AliasFormat::REGEX;

        $providedAliases = collect($rows)
            ->pluck('alias')
            ->filter(fn ($a) => $a !== '')
            ->map(fn ($a) => strtolower($a))
            ->all();
        $existing = !empty($providedAliases)
            ? Link::whereIn(DB::raw('LOWER(alias)'), $providedAliases)->pluck('alias')->map(fn ($a) => strtolower($a))->all()
            : [];
        $existingSet = array_flip($existing);

        $banned = new \App\Modules\Admin\Rules\NotBannedName();
        $usedInBatch = [];
        $out = [];

        foreach ($rows as $r) {
            $errors = [];
            $url   = $r['long_url'];
            $alias = $r['alias'];
            $title = $r['title'];

            if ($url === '') {
                $errors[] = 'Destination URL is required.';
            } elseif (mb_strlen($url) > 2048) {
                $errors[] = 'Destination URL is too long (max 2048 characters).';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                $errors[] = 'Not a valid http(s) URL.';
            }

            if ($title !== '' && mb_strlen($title) > 255) {
                $errors[] = 'Title is too long (max 255 characters).';
            }

            $finalAlias = $alias;
            if ($alias !== '') {
                if (!preg_match($aliasPattern, $alias)) {
                    $errors[] = 'Alias may only contain letters, numbers, dots, dashes and underscores.';
                } elseif (mb_strlen($alias) < $limits['min'] || mb_strlen($alias) > $limits['max']) {
                    $errors[] = "Alias must be {$limits['min']}–{$limits['max']} characters.";
                } else {
                    // NotBannedName via a throw-on-fail Closure capture.
                    $bannedHit = null;
                    $banned->validate('alias', $alias, function ($msg) use (&$bannedHit) { $bannedHit = $msg; });
                    if ($bannedHit) {
                        $errors[] = 'This alias is reserved and can\'t be used.';
                    } elseif (isset($existingSet[strtolower($alias)])) {
                        $errors[] = 'Alias is already taken.';
                    } elseif (isset($usedInBatch[strtolower($alias)])) {
                        $errors[] = 'Duplicate alias within this batch.';
                    } else {
                        $usedInBatch[strtolower($alias)] = true;
                    }
                }
            } else {
                $finalAlias = Link::generateAlias();
                // Defensively avoid colliding with an alias another row in
                // this batch chose explicitly.
                while (isset($usedInBatch[strtolower($finalAlias)])) {
                    $finalAlias = Link::generateAlias();
                }
                $usedInBatch[strtolower($finalAlias)] = true;
            }

            $out[] = [
                'long_url'    => $url,
                'alias'       => $alias,
                'title'       => $title,
                'final_alias' => $finalAlias,
                'errors'      => $errors,
            ];
        }

        return $out;
    }

    /**
     * Normalize and validate the shared options panel from the bulk form.
     * Returns [shared array, errors array] — the shared array is also
     * what the preview screen re-emits as hidden fields on the way to
     * bulkStoreUrl, so the shape must round-trip cleanly.
     */
    private function validateBulkSharedOptions(Request $request): array
    {
        $owner = workspace_owner();
        $errors = [];

        $shared = [
            'type'              => 'url',
            'project_id'        => $request->input('project_id') ? (int) $request->input('project_id') : null,
            'domain_id'         => $request->input('domain_id')  ? (int) $request->input('domain_id')  : null,
            'redirect_type'     => in_array((int) $request->input('redirect_type', 301), [301, 302], true)
                                    ? (int) $request->input('redirect_type', 301) : 301,
            'expires_at'        => $request->input('expires_at') ?: null,
            'password'          => $request->input('password') ?: null,
            'seo_title'         => trim((string) $request->input('seo_title', '')) ?: null,
            'seo_description'   => trim((string) $request->input('seo_description', '')) ?: null,
            'utm_source'        => trim((string) $request->input('utm_source', '')) ?: null,
            'utm_medium'        => trim((string) $request->input('utm_medium', '')) ?: null,
            'utm_campaign'      => trim((string) $request->input('utm_campaign', '')) ?: null,
            'utm_term'          => trim((string) $request->input('utm_term', '')) ?: null,
            'utm_content'       => trim((string) $request->input('utm_content', '')) ?: null,
            'pixel_ids'         => array_values(array_filter(array_map('intval', (array) $request->input('pixel_ids', [])))),
            'show_preview_page' => $request->boolean('show_preview_page'),
        ];

        if ($shared['expires_at']) {
            try {
                $dt = \Carbon\Carbon::parse($shared['expires_at']);
                if ($dt->isPast()) $errors[] = 'Expiration date must be in the future.';
            } catch (\Throwable $e) {
                $errors[] = 'Expiration date is invalid.';
                $shared['expires_at'] = null;
            }
        }

        if ($shared['domain_id']) {
            $allowed = \App\Modules\User\Models\Domain::availableTo($request->user())->pluck('id')->all();
            if (!in_array($shared['domain_id'], $allowed, true)) {
                $errors[] = 'That domain is not available on your plan.';
                $shared['domain_id'] = null;
            }
        }

        if ($shared['project_id']) {
            $exists = $owner->projects()->where('id', $shared['project_id'])->exists();
            if (!$exists) {
                $errors[] = 'Selected project is not available.';
                $shared['project_id'] = null;
            }
        }

        if (!empty($shared['pixel_ids'])) {
            $allowedPx = $owner->pixels()->whereIn('id', $shared['pixel_ids'])->pluck('id')->all();
            $shared['pixel_ids'] = array_values(array_intersect($shared['pixel_ids'], $allowedPx));
        }

        return [$shared, $errors];
    }
}
