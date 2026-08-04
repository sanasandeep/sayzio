<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        // Task #6618 — overlay the ACTIVE workspace's creator profile so
        // the mobile app sees the workspace-scoped handle/bio/publish state.
        $user = $request->user();
        $this->activeCreatorProfile($user)->applyToUser($user);

        return $this->ok(['user' => UserResource::toArray($user, self: true)]);
    }

    /** The authed user's active-workspace creator profile (Task #6618). */
    private function activeCreatorProfile(\App\Modules\User\Models\User $user): \App\Modules\User\Models\CreatorProfile
    {
        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user)
            ?? $user->ensureDefaultWorkspace();

        return \App\Modules\User\Models\CreatorProfile::forWorkspace($ws);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:120'],
            'bio'              => ['sometimes', 'nullable', 'string', 'max:500'],
            // Task #6618 — the handle lives on the ACTIVE workspace's
            // creator profile; uniqueness is enforced across all profiles.
            'handle'           => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/i', \App\Modules\User\Models\CreatorProfile::uniqueHandleRule($this->activeCreatorProfile($user)->id, $user->id), new \App\Modules\Admin\Rules\NotBannedName()],
            'avatar'           => ['sometimes', 'nullable', 'string', 'max:500'],
            // Creator-profile avatar override (Task #5494). Null clears the
            // override so public creator surfaces fall back to the account
            // profile photo again.
            'creator_avatar'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:40'],
            'timezone'         => ['sometimes', 'nullable', 'string', 'max:60'],
            'language'         => ['sometimes', 'nullable', 'string', 'max:10'],
            'discoverable'     => ['sometimes', 'boolean'],
            'allow_followers'  => ['sometimes', 'boolean'],
            // Safety & moderation (mirrors web CreatorProfileController).
            'mute_words_text'     => ['sometimes', 'nullable', 'string', 'max:4000'],
            'watermark_enabled'   => ['sometimes', 'boolean'],
            'country_block_text'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'country_allow_text'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'dmca_email'          => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        // Verified users have their display name AND profile photo locked
        // server-side (same rule as the web ProfileController) — silently
        // ignore both so an API call cannot bypass the identity lock.
        if ($user->isNameAvatarLocked()) {
            unset($data['name'], $data['avatar'], $data['creator_avatar']);
        }

        // `users.timezone` / `users.language` are NOT NULL columns — an empty
        // field on the mobile edit screen must mean "leave unchanged", never
        // "write NULL" (which 500s on the DB constraint).
        foreach (['timezone', 'language'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === null || trim((string) $data[$key]) === '')) {
                unset($data[$key]);
            }
        }

        // ── Safety & moderation — same normalisation as the web save ──
        if (array_key_exists('mute_words_text', $data)) {
            $words = preg_split('/[\r\n,]+/', (string) $data['mute_words_text']) ?: [];
            $user->mute_words = collect($words)
                ->map(fn ($w) => mb_strtolower(trim((string) $w)))
                ->filter(fn ($w) => $w !== '' && mb_strlen($w) <= 64)
                ->unique()
                ->take(\App\Modules\Common\Services\MuteWordsService::MAX_WORDS)
                ->values()
                ->all();
            unset($data['mute_words_text']);
        }
        if (array_key_exists('watermark_enabled', $data)) {
            $settings = is_array($user->watermark_settings) ? $user->watermark_settings : [];
            $settings['enabled'] = (bool) $data['watermark_enabled'];
            $settings += ['opacity' => 35, 'position' => 'br', 'text_template' => '@{handle} • {viewer}'];
            $user->watermark_settings = $settings;
            unset($data['watermark_enabled']);
        }
        $normalizeCountries = function ($raw) {
            return collect(preg_split('/[\s,;]+/', (string) $raw) ?: [])
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->filter(fn ($c) => preg_match('/^[A-Z]{2}$/', $c))
                ->unique()->values()->all();
        };
        if (array_key_exists('country_block_text', $data)) {
            $user->country_block_list = $normalizeCountries($data['country_block_text']);
            unset($data['country_block_text']);
        }
        if (array_key_exists('country_allow_text', $data)) {
            $user->country_allow_list = $normalizeCountries($data['country_allow_text']);
            unset($data['country_allow_text']);
        }
        if (array_key_exists('dmca_email', $data)) {
            $user->dmca_email = $data['dmca_email'] !== null && trim((string) $data['dmca_email']) !== ''
                ? trim((string) $data['dmca_email'])
                : null;
            unset($data['dmca_email']);
        }

        // Task #6618 — handle (and bio, which is a profile field) write to
        // the ACTIVE workspace's creator profile. users.handle/bio stay in
        // sync via the personal-workspace mirror only.
        $profile = null;
        if (array_key_exists('handle', $data) || array_key_exists('bio', $data)) {
            $profile = $this->activeCreatorProfile($user);
            if (array_key_exists('handle', $data)) {
                $profile->handle = $data['handle'] !== null ? strtolower(trim((string) $data['handle'])) : null;
                unset($data['handle']);
            }
            if (array_key_exists('bio', $data)) {
                $profile->bio = $data['bio'];
            }
        }

        $previousName = $user->getOriginal('name');
        $user->fill($data)->save();
        if ($profile) {
            $profile->save();
            $profile->mirrorToOwner();
            $user->refresh();
        }

        // Propagate a rename to every denormalized copy of the display name
        // (personal workspace, linked admin, comments/rosters/fan points/
        // subscriber entries/linked contacts + creator-surface caches) —
        // same sync as the web profile-update path.
        if ((string) $user->name !== (string) $previousName) {
            \App\Modules\User\Services\UserNameSync::handleRename($user, $previousName);
        }

        return $this->ok(['user' => UserResource::toArray($user->fresh(), self: true)]);
    }
}
