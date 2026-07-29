<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Rules\NotBannedName;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Sidebar entry "Creator Profile" — the editor for the public
 * /@handle page. Sections:
 *  - Hero (avatar/name come from regular Profile; cover/tagline/location here)
 *  - About (bio + niche tags)
 *  - Socials (links to other platforms)
 *  - Settings (publish/unpublish, section visibility)
 *
 * Handle claiming reuses the user's existing handle field (already
 * unique, banned-name-checked, and migration-backed). Owners without a
 * handle see a banner asking them to claim one before they can publish.
 */
class CreatorProfileController extends Controller
{
    /**
     * The platform-wide list of social platforms that the editor knows
     * about. Each row is `key => [label, icon, prefix?, validate?]`.
     * `validate` is a Laravel rule fragment applied to the value field.
     */
    public const SOCIAL_PLATFORMS = [
        'twitter'   => ['label' => 'X / Twitter',   'icon' => 'fab fa-x-twitter',   'placeholder' => 'username'],
        'instagram' => ['label' => 'Instagram',     'icon' => 'fab fa-instagram',   'placeholder' => 'username'],
        'tiktok'    => ['label' => 'TikTok',        'icon' => 'fab fa-tiktok',      'placeholder' => 'username'],
        'youtube'   => ['label' => 'YouTube',       'icon' => 'fab fa-youtube',     'placeholder' => 'channel handle or URL'],
        'linkedin'  => ['label' => 'LinkedIn',      'icon' => 'fab fa-linkedin',    'placeholder' => 'profile URL'],
        'github'    => ['label' => 'GitHub',        'icon' => 'fab fa-github',      'placeholder' => 'username'],
        'twitch'    => ['label' => 'Twitch',        'icon' => 'fab fa-twitch',      'placeholder' => 'username'],
        'spotify'   => ['label' => 'Spotify',       'icon' => 'fab fa-spotify',     'placeholder' => 'artist URL'],
        'website'   => ['label' => 'Website',       'icon' => 'fas fa-globe',       'placeholder' => 'https://…'],
        'email'     => ['label' => 'Email',         'icon' => 'fas fa-envelope',    'placeholder' => 'you@example.com'],
    ];

    /** Showcase item types the editor knows about (links.type → label + icon). */
    public const SHOWCASE_ITEM_TYPES = [
        'qr'              => ['label' => 'QR Code',        'icon' => 'fas fa-qrcode'],
        'form'            => ['label' => 'Form',           'icon' => 'fas fa-wpforms'],
        'ics'             => ['label' => 'Event',          'icon' => 'fas fa-calendar-days'],
        'vcard'           => ['label' => 'Digital Card',   'icon' => 'fas fa-id-card'],
        'resume'          => ['label' => 'Resume',         'icon' => 'fas fa-file-user'],
        'restaurant_menu' => ['label' => 'Restaurant Menu','icon' => 'fas fa-utensils'],
        'store_menu'      => ['label' => 'Store',          'icon' => 'fas fa-store'],
    ];

    /**
     * Allowed featured-link display styles.
     * key => human label (used in the settings UI picker).
     */
    public const FEATURED_LINK_STYLES = [
        'classic'      => 'Classic card',
        'outline'      => 'Outline button',
        'solid'        => 'Solid fill',
        'ghost'        => 'Ghost text',
        'pill'         => 'Pill',
        'card_heading' => 'Heading card',
    ];

    /** Primary CTA action types. */
    public const CTA_KINDS = [
        'email'     => ['label' => 'Email me',       'icon' => 'fas fa-envelope',    'hint' => 'email address'],
        'whatsapp'  => ['label' => 'WhatsApp me',    'icon' => 'fab fa-whatsapp',     'hint' => 'phone number with country code'],
        'call'      => ['label' => 'Call me',        'icon' => 'fas fa-phone',        'hint' => 'phone number'],
        'link'      => ['label' => 'Visit a link',   'icon' => 'fas fa-arrow-up-right-from-square', 'hint' => 'https://…'],
        'form'      => ['label' => 'Fill out a form','icon' => 'fas fa-wpforms',      'hint' => 'select a form below'],
    ];

    public function edit()
    {
        $user = Auth::user();
        $showcase = $user->resolvedProfileShowcase();

        // Load owner's links for the featured-link picker (all active links).
        $pickerLinks = \App\Modules\User\Models\Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias', 'type']);

        // Load owner's links filtered to showcase-eligible types.
        $showcaseEligibleLinks = $pickerLinks->filter(
            fn ($l) => array_key_exists($l->type, self::SHOWCASE_ITEM_TYPES)
        )->values();

        // Load owner's active forms for the CTA form-picker.
        $formsForCta = \App\Modules\User\Models\Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('type', 'form')
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias']);

        $pickerLinkMap = $pickerLinks->keyBy('id')->map(fn ($l) => [
            'title' => $l->title ?: $l->alias,
            'type'  => $l->type,
            'alias' => $l->alias,
        ])->toArray();

        return view('user.creator-profile.edit', [
            'user'                  => $user,
            'completeness'          => $user->profileCompletenessPercent(),
            'sections'              => $user->profileSectionVisibility(),
            'socials'               => is_array($user->socials) ? $user->socials : [],
            'nicheTags'             => is_array($user->niche_tags) ? $user->niche_tags : [],
            'platforms'             => self::SOCIAL_PLATFORMS,
            'profileUrl'            => $user->handle ? url('/@' . $user->handle) : null,
            'sectionDefaults'       => User::PROFILE_DEFAULT_VISIBILITY,
            'organizer'             => $user->organizerProfile(),
            // Showcase data.
            'showcase'              => $showcase,
            'pickerLinks'           => $pickerLinks,
            'pickerLinkMap'         => $pickerLinkMap,
            'showcaseFeaturedLinks' => $showcase['featured_links'],
            'featuredLinksStyle'    => $showcase['featured_links_style'],
            'featuredLinkStyles'    => self::FEATURED_LINK_STYLES,
            'showcaseEligibleLinks' => $showcaseEligibleLinks,
            'formsForCta'           => $formsForCta,
            'showcaseItemTypes'     => self::SHOWCASE_ITEM_TYPES,
            'ctaKinds'              => self::CTA_KINDS,
        ]);
    }

    /**
     * Persist the primary creator-profile fields (cover, tagline, location,
     * bio, niche_tags, socials, section visibility) for the given user.
     * Called from both the full editor update() and the onboarding step so
     * the save logic lives in one place.
     *
     * Only keys that are present in $data are applied — absent keys leave the
     * corresponding model field untouched.
     *
     * @param array<string,mixed>        $data    Validated input.
     * @param \Illuminate\Http\Request|null $request Pass the request to handle file uploads.
     */
    public static function saveCoreProfileFields(
        User $user,
        array $data,
        ?\Illuminate\Http\Request $request = null
    ): void {
        // Creator-profile-specific avatar override (Task #5494). Defaults to
        // the account profile photo when null. Verified users have their
        // avatar identity locked — ignore upload/remove server-side so a
        // direct POST cannot bypass the lock (same rule as ProfileController).
        if (!$user->isNameAvatarLocked()) {
            $creatorAvatarAsset = $request?->input('creator_avatar_asset');
            $creatorAvatarAssetValid = is_string($creatorAvatarAsset)
                && \App\Modules\User\Support\PlatformAssetCatalog::folderForKey(
                    $creatorAvatarAsset,
                    \App\Modules\User\Support\PlatformAssetCatalog::AVATAR_FOLDERS
                ) !== null;

            if ($request && $request->hasFile('creator_avatar')) {
                $user->creator_avatar = '/storage/' . $request->file('creator_avatar')->store('avatars', 'public');
            } elseif ($creatorAvatarAssetValid) {
                // Platform avatar-gallery pick (Task #6015) — store the
                // absolute public CDN URL (PublicStorageUrl passes absolute
                // URLs through untouched, so all render paths stay safe).
                $user->creator_avatar = \App\Modules\User\Support\PlatformAssetCatalog::urlForKey($creatorAvatarAsset);
            } elseif ($request && $request->boolean('creator_avatar_remove')) {
                $user->creator_avatar = null;
            }
        }

        if ($request && $request->hasFile('cover_image')) {
            $user->cover_image = '/storage/' . $request->file('cover_image')->store('profile-covers', 'public');
        } elseif (!empty($data['cover_image_url'])) {
            $user->cover_image = $data['cover_image_url'];
        } elseif ($request && $request->boolean('cover_image_remove')) {
            $user->cover_image = null;
        }

        if (array_key_exists('tagline', $data))  $user->tagline  = $data['tagline'];
        if (array_key_exists('location', $data)) $user->location = $data['location'];
        if (array_key_exists('bio', $data))      $user->bio      = $data['bio'];

        if (array_key_exists('profile_theme_color', $data)) {
            $user->profile_theme_color = isset($data['profile_theme_color']) && $data['profile_theme_color'] !== ''
                ? strtolower($data['profile_theme_color'])
                : null;
        }

        if (array_key_exists('niche_tags', $data)) {
            $tags = collect($data['niche_tags'] ?? [])
                ->map(fn ($t) => trim((string) $t))
                ->filter(fn ($t) => $t !== '')
                ->map(fn ($t) => mb_strtolower($t))
                ->unique()
                ->take(8)
                ->values()
                ->all();
            $user->niche_tags = $tags;
        }

        if (array_key_exists('socials', $data)) {
            $allowed = array_keys(self::SOCIAL_PLATFORMS);
            $socials = [];
            foreach ((array) ($data['socials'] ?? []) as $key => $value) {
                if (!in_array($key, $allowed, true)) continue;
                $value = trim((string) $value);
                if ($value !== '') $socials[$key] = $value;
            }
            $user->socials = $socials;
        }

        if (array_key_exists('sections', $data)) {
            $sectionsIn = (array) ($data['sections'] ?? []);
            $sections = [];
            foreach (User::PROFILE_DEFAULT_VISIBILITY as $sectionKey => $default) {
                $sections[$sectionKey] = filter_var($sectionsIn[$sectionKey] ?? $default, FILTER_VALIDATE_BOOLEAN);
            }
            $user->profile_section_visibility = $sections;
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'tagline'             => 'nullable|string|max:200',
            'location'            => 'nullable|string|max:120',
            'bio'                 => 'nullable|string|max:2000',
            'creator_avatar'      => 'nullable|image|max:2048',
            // Platform avatar-gallery pick (Task #6015) — S3 object key,
            // validated in saveCoreProfileFields via PlatformAssetCatalog.
            'creator_avatar_asset' => 'nullable|string|max:300',
            'creator_avatar_remove' => 'nullable|in:0,1',
            'cover_image'         => 'nullable|image|max:5120',
            'cover_image_url'     => 'nullable|string|max:1024',
            'niche_tags'          => 'nullable|array|max:8',
            'niche_tags.*'        => 'string|max:32',
            'socials'             => 'nullable|array',
            'socials.*'           => 'nullable|string|max:200',
            'sections'            => 'nullable|array',
            'sections.*'          => 'nullable|in:0,1,true,false',
            'profile_published'   => 'nullable|in:0,1,true,false',
            'profile_theme_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Showcase — Task #5431.
            'featured_links'            => 'nullable|array|max:8',
            'featured_links.*.id'       => 'nullable|integer|min:1',
            'featured_links.*.enabled'  => 'nullable|in:0,1,true,false',
            'featured_links_style'      => 'nullable|string|in:classic,outline,solid,ghost,pill,card_heading',
            'showcase_show_link_stats'  => 'nullable|in:0,1,true,false',
            'showcase_items'               => 'nullable|array|max:20',
            'showcase_items.*'             => 'array',
            'showcase_items.*.type'        => 'required_with:showcase_items.*|string',
            'showcase_items.*.link_id'     => 'required_with:showcase_items.*|integer|min:1',
            'highlights_show_followers'    => 'nullable|in:0,1,true,false',
            'highlights_show_links'        => 'nullable|in:0,1,true,false',
            'highlights_show_member_since' => 'nullable|in:0,1,true,false',
            'highlights_show_verified'     => 'nullable|in:0,1,true,false',
            'cta_primary_kind'             => 'nullable|string|in:email,whatsapp,call,link,form',
            'cta_primary_label'            => 'nullable|string|max:80',
            'cta_primary_value'            => 'nullable|string|max:500',
            'cta_secondary'                => 'nullable|array|max:3',
            'cta_secondary.*.kind'         => 'required_with:cta_secondary.*|string|in:email,whatsapp,call,link,form',
            'cta_secondary.*.label'        => 'required_with:cta_secondary.*|string|max:80',
            'cta_secondary.*.value'        => 'required_with:cta_secondary.*|string|max:500',
            // Task #1211 — moderation / safety preferences.
            'mute_words_text'         => 'nullable|string|max:4000',
            'watermark_enabled'       => 'nullable|in:0,1,true,false',
            'watermark_opacity'       => 'nullable|integer|min:10|max:90',
            'watermark_position'      => 'nullable|in:tl,tr,bl,br,center',
            'watermark_text_template' => 'nullable|string|max:120',
            'country_block_text'      => 'nullable|string|max:1000',
            'country_allow_text'      => 'nullable|string|max:1000',
            'dmca_email'              => 'nullable|email|max:255',
            // Reusable event organizer profile (Task #3699).
            'organizer_logo'          => 'nullable|image|max:5120',
            'organizer_logo_url'      => 'nullable|string|max:1024',
            'organizer_logo_remove'   => 'nullable|in:0,1',
            'organizer_name'          => 'nullable|string|max:150',
            'organizer_description'   => 'nullable|string|max:1000',
            'organizer_website'       => 'nullable|url|max:1024',
            'organizer_contact_name'  => 'nullable|string|max:150',
            'organizer_contact_phone' => 'nullable|string|max:40',
            'organizer_contact_email' => 'nullable|email|max:255',
            'organizer_address'       => 'nullable|string|max:500',
            'organizer_socials'       => 'nullable|array',
            'organizer_socials.*'     => 'nullable|string|max:200',
        ]);

        // Core profile fields — shared with the onboarding creator-profile step.
        self::saveCoreProfileFields($user, $data, $request);

        // Publish toggle. Block publishing without a handle — the URL
        // would 404 otherwise.
        $wantsPublished = filter_var(
            $data['profile_published'] ?? $user->profile_published,
            FILTER_VALIDATE_BOOLEAN
        );
        if ($wantsPublished && empty($user->handle)) {
            return back()->withInput()->with('error',
                'Pick a handle below before you publish — your profile lives at /@handle.');
        }
        $user->profile_published = $wantsPublished;

        // ── Task #1211: moderation / safety preferences ──────────────
        // Mute words: split on commas/newlines, lowercase, dedupe.
        if ($request->has('mute_words_text')) {
            $words = preg_split('/[\r\n,]+/', (string) $data['mute_words_text']) ?: [];
            $user->mute_words = collect($words)
                ->map(fn ($w) => mb_strtolower(trim((string) $w)))
                ->filter(fn ($w) => $w !== '' && mb_strlen($w) <= 64)
                ->unique()
                ->take(\App\Modules\Common\Services\MuteWordsService::MAX_WORDS)
                ->values()
                ->all();
        }

        // Watermark settings JSON.
        $user->watermark_settings = [
            'enabled'       => filter_var($data['watermark_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'opacity'       => max(10, min(90, (int) ($data['watermark_opacity'] ?? 35))),
            'position'      => $data['watermark_position'] ?? 'br',
            'text_template' => trim((string) ($data['watermark_text_template'] ?? '@{handle} • {viewer}')) ?: '@{handle} • {viewer}',
        ];

        // Country lists: split CSV-style + uppercase 2-letter codes.
        $normalize = function ($raw) {
            return collect(preg_split('/[\s,;]+/', (string) $raw) ?: [])
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->filter(fn ($c) => preg_match('/^[A-Z]{2}$/', $c))
                ->unique()->values()->all();
        };
        $user->country_block_list = $normalize($data['country_block_text'] ?? '');
        $user->country_allow_list = $normalize($data['country_allow_text'] ?? '');
        $user->dmca_email = $data['dmca_email'] ?? null;

        // ── Task #3699: reusable event organizer profile ─────────────
        $allowed  = array_keys(self::SOCIAL_PLATFORMS);
        $organizer = is_array($user->organizer_profile) ? $user->organizer_profile : [];

        if ($request->hasFile('organizer_logo')) {
            $organizer['logo'] = '/storage/' . $request->file('organizer_logo')->store('organizer-logos', 'public');
        } elseif ($request->filled('organizer_logo_url')) {
            $organizer['logo'] = $data['organizer_logo_url'];
        } elseif ($request->boolean('organizer_logo_remove')) {
            $organizer['logo'] = null;
        }

        $organizer['name']          = $data['organizer_name'] ?? null;
        $organizer['description']   = $data['organizer_description'] ?? null;
        $organizer['website']       = $data['organizer_website'] ?? null;
        $organizer['contact_name']  = $data['organizer_contact_name'] ?? null;
        $organizer['contact_phone'] = $data['organizer_contact_phone'] ?? null;
        $organizer['contact_email'] = $data['organizer_contact_email'] ?? null;
        $organizer['address']       = $data['organizer_address'] ?? null;

        $organizerSocials = [];
        foreach ((array) ($data['organizer_socials'] ?? []) as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $value = trim((string) $value);
            if ($value !== '') $organizerSocials[$key] = $value;
        }
        $organizer['socials'] = $organizerSocials;

        $user->organizer_profile = $organizer;

        // ── Task #5431: profile showcase ──────────────────────────────
        self::saveShowcaseFields($user, $data);

        $user->save();

        return redirect()->route('user.creator-profile.edit')
            ->with('success', 'Creator profile saved.');
    }

    /**
     * Assemble and assign the `profile_showcase` JSON from validated input.
     * Shared by the web editor update() and the REST API
     * (`PATCH /api/v1/me/creator-profile`) so the assembly, ownership checks
     * and defaults live in one place. Does NOT save the model.
     *
     * Validates ownership of every referenced link ID (featured + showcase).
     * We silently drop IDs that don't belong to the owner rather than
     * returning a validation error — the picker is pre-filtered to owned
     * links, so any mismatch is a client-side glitch, not a user mistake.
     *
     * @param array<string,mixed> $data Validated input.
     */
    public static function saveShowcaseFields(User $user, array $data): void
    {
        $ownerLinkIds = \App\Modules\User\Models\Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $rawFeaturedLinks = (array) ($data['featured_links'] ?? []);
        $featuredLinks = [];
        foreach ($rawFeaturedLinks as $item) {
            if (!is_array($item)) continue;
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0 || !in_array($id, $ownerLinkIds, true)) continue;
            $featuredLinks[] = [
                'id'      => $id,
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $rawShowcaseItems = (array) ($data['showcase_items'] ?? []);
        $allowedShowcaseTypes = array_keys(self::SHOWCASE_ITEM_TYPES);
        $showcaseItems = [];
        foreach ($rawShowcaseItems as $item) {
            if (!is_array($item)) continue;
            $type   = (string) ($item['type'] ?? '');
            $linkId = (int) ($item['link_id'] ?? 0);
            if (!in_array($type, $allowedShowcaseTypes, true)) continue;
            if (!in_array($linkId, $ownerLinkIds, true)) continue;
            $showcaseItems[] = ['type' => $type, 'link_id' => $linkId];
        }

        // Build primary CTA. Contact details are stored only if the owner
        // explicitly entered them in this block — never auto-pulled from
        // account phone/email (spec requirement).
        $ctaPrimary = null;
        if (!empty($data['cta_primary_kind'])) {
            $ctaPrimary = [
                'kind'  => $data['cta_primary_kind'],
                'label' => trim((string) ($data['cta_primary_label'] ?? '')),
                'value' => trim((string) ($data['cta_primary_value'] ?? '')),
            ];
            // For kind=form validate the value is an owned form alias.
            if ($ctaPrimary['kind'] === 'form') {
                $formExists = \App\Modules\User\Models\Link::query()
                    ->withoutGlobalScope('workspace')
                    ->where('user_id', $user->id)
                    ->where('type', 'form')
                    ->where('alias', $ctaPrimary['value'])
                    ->exists();
                if (!$formExists) $ctaPrimary = null;
            }
        }

        $ctaSecondary = [];
        foreach ((array) ($data['cta_secondary'] ?? []) as $sec) {
            if (!is_array($sec)) continue;
            $kind  = (string) ($sec['kind'] ?? '');
            $label = trim((string) ($sec['label'] ?? ''));
            $value = trim((string) ($sec['value'] ?? ''));
            if (!array_key_exists($kind, self::CTA_KINDS) || $label === '' || $value === '') continue;
            $ctaSecondary[] = ['kind' => $kind, 'label' => $label, 'value' => $value];
        }

        $validStyles = array_keys(self::FEATURED_LINK_STYLES);
        $chosenStyle = (string) ($data['featured_links_style'] ?? 'classic');
        if (!in_array($chosenStyle, $validStyles, true)) $chosenStyle = 'classic';

        $user->profile_showcase = [
            'featured_links'       => $featuredLinks,
            'featured_links_style' => $chosenStyle,
            'show_link_stats'      => filter_var($data['showcase_show_link_stats'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'showcase_items'    => $showcaseItems,
            'highlights' => [
                'show_followers'    => filter_var($data['highlights_show_followers']    ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_links'        => filter_var($data['highlights_show_links']        ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_member_since' => filter_var($data['highlights_show_member_since'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_verified'     => filter_var($data['highlights_show_verified']     ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'cta' => [
                'primary'   => $ctaPrimary,
                'secondary' => array_values($ctaSecondary),
            ],
        ];
    }

    /**
     * Claim or update the @handle without going through the full Profile
     * editor. Same validation rules as the Profile editor's handle field
     * (case-insensitive uniqueness + banned-names list + format).
     */
    public function claimHandle(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'handle' => [
                'required', 'string', 'min:3', 'max:30',
                'regex:/^[a-z0-9_]+$/i',
                Rule::unique('users')->ignore($user->id),
                new NotBannedName(),
            ],
        ]);
        $previousHandle = $user->handle;
        $user->handle = strtolower($data['handle']);
        $user->save();

        // Clear the admin-forced rename flag once the user has successfully
        // picked a different handle (banner lives on Profile Settings).
        if (session()->has('force_handle_rename') && $user->handle !== $previousHandle) {
            session()->forget('force_handle_rename');
        }

        return redirect()->route('user.creator-profile.edit')
            ->with('success', "Your profile is now at /@{$user->handle}");
    }
}
