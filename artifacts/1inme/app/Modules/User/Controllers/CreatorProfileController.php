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

    public function edit()
    {
        $user = Auth::user();
        return view('user.creator-profile.edit', [
            'user'             => $user,
            'completeness'     => $user->profileCompletenessPercent(),
            'sections'         => $user->profileSectionVisibility(),
            'socials'          => is_array($user->socials) ? $user->socials : [],
            'nicheTags'        => is_array($user->niche_tags) ? $user->niche_tags : [],
            'platforms'        => self::SOCIAL_PLATFORMS,
            'profileUrl'       => $user->handle ? url('/@' . $user->handle) : null,
            'sectionDefaults'  => User::PROFILE_DEFAULT_VISIBILITY,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'tagline'          => 'nullable|string|max:200',
            'location'         => 'nullable|string|max:120',
            'bio'              => 'nullable|string|max:2000',
            'cover_image'      => 'nullable|image|max:5120',
            'cover_image_url'  => 'nullable|string|max:1024',
            'niche_tags'       => 'nullable|array|max:8',
            'niche_tags.*'     => 'string|max:32',
            'socials'          => 'nullable|array',
            'socials.*'        => 'nullable|string|max:200',
            'sections'         => 'nullable|array',
            'sections.*'       => 'nullable|in:0,1,true,false',
            'profile_published'=> 'nullable|in:0,1,true,false',
        ]);

        if ($request->hasFile('cover_image')) {
            $user->cover_image = '/storage/' . $request->file('cover_image')->store('profile-covers', 'public');
        } elseif ($request->filled('cover_image_url')) {
            $user->cover_image = $data['cover_image_url'];
        } elseif ($request->boolean('cover_image_remove')) {
            $user->cover_image = null;
        }

        $user->tagline  = $data['tagline']  ?? null;
        $user->location = $data['location'] ?? null;
        if (array_key_exists('bio', $data)) $user->bio = $data['bio'];

        // Niche tags: normalise to lowercase trimmed unique short strings.
        $tags = collect($data['niche_tags'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => $t !== '')
            ->map(fn ($t) => mb_strtolower($t))
            ->unique()
            ->take(8)
            ->values()
            ->all();
        $user->niche_tags = $tags;

        // Socials: only persist known platform keys.
        $allowed = array_keys(self::SOCIAL_PLATFORMS);
        $socials = [];
        foreach ((array) ($data['socials'] ?? []) as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $value = trim((string) $value);
            if ($value !== '') $socials[$key] = $value;
        }
        $user->socials = $socials;

        // Section visibility — merge with defaults and drop any key the
        // editor doesn't recognise so we can't be tricked into hiding the
        // hero.
        $sectionsIn = (array) ($data['sections'] ?? []);
        $sections = [];
        foreach (User::PROFILE_DEFAULT_VISIBILITY as $key => $default) {
            $sections[$key] = filter_var($sectionsIn[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }
        $user->profile_section_visibility = $sections;

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
        $user->save();

        return redirect()->route('user.creator-profile.edit')
            ->with('success', 'Creator profile saved.');
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
        $user->handle = strtolower($data['handle']);
        $user->save();
        return redirect()->route('user.creator-profile.edit')
            ->with('success', "Your profile is now at /@{$user->handle}");
    }
}
