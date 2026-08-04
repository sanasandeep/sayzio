<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Workspace-scoped creator profile — the authoritative store behind the
 * public /@handle page (Task #6618). One row per workspace; each row owns
 * its own unique handle, publish state and profile content, so an account
 * with several workspaces can run several independent public pages.
 *
 * Legacy compatibility: the historical users.* profile columns are kept
 * and mirrored FOR PERSONAL WORKSPACES ONLY (see mirrorToOwner) because a
 * long tail of consumers (creators directory, monetization return URLs,
 * DMs, resume, OG images) still read $user->handle & friends. Public
 * resolution overlays this row's attributes onto the owner User instance
 * (applyToUser) so every downstream blade/service keeps reading the same
 * property names while getting per-workspace values.
 */
class CreatorProfile extends Model
{
    /** The profile fields that moved off the users table. */
    public const FIELDS = [
        'handle', 'bio', 'tagline', 'location', 'niche_tags', 'socials',
        'cover_image', 'creator_avatar', 'profile_published',
        'profile_section_visibility', 'profile_showcase',
        'profile_theme_color', 'posts_count', 'followers_count',
    ];

    protected $fillable = [
        'workspace_id', 'user_id',
        'handle', 'bio', 'tagline', 'location', 'niche_tags', 'socials',
        'cover_image', 'creator_avatar', 'profile_published',
        'profile_section_visibility', 'profile_showcase',
        'profile_theme_color', 'posts_count', 'followers_count',
    ];

    protected $casts = [
        'niche_tags'                 => 'array',
        'socials'                    => 'array',
        'profile_section_visibility' => 'array',
        'profile_showcase'           => 'array',
        'profile_published'          => 'boolean',
        'posts_count'                => 'integer',
        'followers_count'            => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The profile for a workspace, lazily created (empty + unpublished)
     * the first time a workspace's profile surface is opened.
     */
    public static function forWorkspace(Workspace $ws, bool $createIfMissing = true): ?self
    {
        $profile = static::where('workspace_id', $ws->id)->first();
        if ($profile || !$createIfMissing) {
            return $profile;
        }
        return static::firstOrCreate(
            ['workspace_id' => $ws->id],
            ['user_id' => (int) $ws->owner_user_id]
        );
    }

    /**
     * The user's PERSONAL-workspace profile (the migration target of the
     * legacy users.* data) — default profile that follows re-point at when
     * no explicit workspace/handle context is available.
     */
    public static function personalForUser(int $userId): ?self
    {
        return static::query()
            ->whereHas('workspace', fn ($q) => $q->where('is_personal', true))
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first()
            ?? static::where('user_id', $userId)->orderBy('id')->first();
    }

    /** Case-insensitive handle → profile lookup (matches the unique index). */
    public static function resolveByHandle(string $handle): ?self
    {
        $handle = strtolower(trim(ltrim(trim($handle), '@')));
        if ($handle === '' || strlen($handle) > 60) return null;
        return static::whereRaw('LOWER(handle) = ?', [$handle])->first();
    }

    /**
     * Resolve a public handle to the profile's OWNER User with this
     * profile's fields overlaid — the drop-in replacement for the old
     * `User::whereRaw('LOWER(handle) = ?')` resolvers. Every downstream
     * consumer keeps reading $creator->tagline / handle / socials /
     * profile_published etc. but now sees the workspace profile's values.
     *
     * Falls back to the legacy users.handle lookup when no profile row
     * matches (pre-migration windows / partially-seeded environments).
     */
    public static function ownerUserForHandle(string $handle): ?User
    {
        $profile = static::resolveByHandle($handle);
        if ($profile) {
            $owner = $profile->owner;
            return $owner ? $profile->applyToUser($owner) : null;
        }

        $clean = strtolower(trim(ltrim(trim($handle), '@')));
        if ($clean === '' || strlen($clean) > 60) return null;
        return User::query()->whereRaw('LOWER(handle) = ?', [$clean])->first();
    }

    /**
     * Overlay this profile's field values onto the given User instance
     * IN MEMORY. Raw attributes are merged and the original state synced,
     * so nothing becomes dirty — a later $user->save() will never write
     * profile values back into users.* columns. The profile itself is
     * attached as the `activeCreatorProfile` relation so callers that
     * need the workspace id (showcase link scoping) can reach it.
     */
    public function applyToUser(User $user): User
    {
        $overlay = array_intersect_key($this->getAttributes(), array_flip(self::FIELDS));
        $user->setRawAttributes(array_merge($user->getAttributes(), $overlay), true);
        $user->setRelation('activeCreatorProfile', $this);
        return $user;
    }

    /**
     * Mirror this profile's fields back onto the owner's users.* columns —
     * PERSONAL workspaces only. Keeps the long tail of legacy consumers
     * (creators directory, watermarks, monetization URLs, DM routing) in
     * sync with the authoritative store, and preserves the invariant that
     * $user->handle means "personal workspace handle".
     */
    public function mirrorToOwner(): void
    {
        $ws = $this->workspace;
        if (!$ws || !$ws->is_personal) return;
        $owner = $this->owner;
        if (!$owner || (int) $owner->id !== (int) $ws->owner_user_id) return;

        $overlay = array_intersect_key($this->getAttributes(), array_flip(self::FIELDS));
        $owner->setRawAttributes(array_merge($owner->getAttributes(), $overlay));
        $owner->saveQuietly();
    }

    /** Same resolved-showcase logic as the legacy User accessor. */
    public function resolvedProfileShowcase(): array
    {
        return $this->asOverlayUser()->resolvedProfileShowcase();
    }

    public function profileSectionVisibility(): array
    {
        return $this->asOverlayUser()->profileSectionVisibility();
    }

    /**
     * Validation closure enforcing case-insensitive handle uniqueness
     * across ALL workspace profiles (and the legacy users.handle column,
     * for environments where the backfill hasn't run). Every
     * handle-setting surface must use this so verdicts can't drift.
     */
    public static function uniqueHandleRule(?int $ignoreProfileId = null, ?int $ignoreUserId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignoreProfileId, $ignoreUserId) {
            $lower = strtolower(trim((string) $value));
            if ($lower === '') return;

            $taken = static::query()
                ->whereRaw('LOWER(handle) = ?', [$lower])
                ->when($ignoreProfileId, fn ($q) => $q->where('id', '!=', $ignoreProfileId))
                ->exists();
            if (!$taken) {
                $taken = User::query()
                    ->whereRaw('LOWER(handle) = ?', [$lower])
                    ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
                    ->exists();
            }
            if ($taken) {
                $fail('That handle is already taken.');
            }
        };
    }

    /** A detached User instance carrying only this profile's fields (for shared helpers). */
    protected function asOverlayUser(): User
    {
        $u = new User();
        $u->setRawAttributes(array_intersect_key($this->getAttributes(), array_flip(self::FIELDS)), true);
        return $u;
    }
}
