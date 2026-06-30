<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Resolves and manages AI resource sharing (Task #2909).
 *
 * A creator can share their AI Minds / Persona agents into a team
 * (workspace they belong to) or a badge group (account badge they
 * hold). Recipients get either USE-only or USE+EDIT access. The owner
 * always keeps full control; AI/coin costs are charged to the acting
 * user by the runtime services, never the owner.
 *
 * Access is resolved LIVE against the recipient's current
 * memberships/badges, so removing a member or detaching a badge
 * revokes access on the next request — no per-user grant to clean up.
 */
class AiResourceShareService
{
    /**
     * The audiences a user currently belongs to. Suspended workspace
     * seats are excluded so a suspended member loses shared access.
     *
     * @return array{workspace:int[], badge:int[]}
     */
    public function audiencesForUser(User $user): array
    {
        $ownedIds = $user->ownedWorkspaces()->pluck('id')
            ->map(fn ($i) => (int) $i)->all();

        $memberIds = $user->workspaceMemberships()
            ->whereNull('suspended_at')
            ->pluck('workspace_id')
            ->map(fn ($i) => (int) $i)->all();

        $workspaceIds = array_values(array_unique(array_merge($ownedIds, $memberIds)));

        $badgeIds = $user->accountBadges()->get()->modelKeys();
        $badgeIds = array_values(array_unique(array_map('intval', $badgeIds)));

        return ['workspace' => $workspaceIds, 'badge' => $badgeIds];
    }

    /**
     * Effective access a user has on an arbitrary resource via sharing.
     * The owner always gets EDIT. Returns 'edit' | 'use' | null.
     */
    public function accessForResource(User $user, string $resourceType, int $resourceId, ?int $ownerUserId): ?string
    {
        if ($ownerUserId !== null && (int) $ownerUserId === (int) $user->id) {
            return AiResourceShare::ACCESS_EDIT;
        }

        $aud = $this->audiencesForUser($user);
        if (empty($aud['workspace']) && empty($aud['badge'])) {
            return null;
        }

        $shares = AiResourceShare::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where(fn ($q) => $this->scopeToAudiences($q, $aud))
            ->get();

        $shares = $this->filterByOwnerAudience($shares);

        if ($shares->isEmpty()) {
            return null;
        }

        return $shares->contains(fn ($s) => $s->access === AiResourceShare::ACCESS_EDIT)
            ? AiResourceShare::ACCESS_EDIT
            : AiResourceShare::ACCESS_USE;
    }

    /** Effective access on a Mind. Platform mind is readable (use) by everyone. */
    public function accessForMind(User $user, AiMind $mind): ?string
    {
        if ($mind->isPlatform()) {
            return AiResourceShare::ACCESS_USE;
        }
        return $this->accessForResource(
            $user,
            AiResourceShare::RESOURCE_MIND,
            (int) $mind->id,
            $mind->user_id !== null ? (int) $mind->user_id : null
        );
    }

    public function canUseMind(User $user, AiMind $mind): bool
    {
        return $this->accessForMind($user, $mind) !== null;
    }

    public function canEditMind(User $user, AiMind $mind): bool
    {
        return $this->accessForMind($user, $mind) === AiResourceShare::ACCESS_EDIT;
    }

    public function accessForPersona(User $user, AiPersonaAgent $persona): ?string
    {
        return $this->accessForResource(
            $user,
            AiResourceShare::RESOURCE_PERSONA,
            (int) $persona->id,
            (int) $persona->user_id
        );
    }

    public function canUsePersona(User $user, AiPersonaAgent $persona): bool
    {
        return $this->accessForPersona($user, $persona) !== null;
    }

    public function canEditPersona(User $user, AiPersonaAgent $persona): bool
    {
        return $this->accessForPersona($user, $persona) === AiResourceShare::ACCESS_EDIT;
    }

    /**
     * Minds shared WITH this user by others (not their own), each
     * annotated with a transient `share_access` attribute.
     *
     * @return Collection<int, AiMind>
     */
    public function sharedMindsForUser(User $user): Collection
    {
        return $this->sharedResources($user, AiResourceShare::RESOURCE_MIND, AiMind::class);
    }

    /**
     * Persona agents shared WITH this user by others.
     *
     * @return Collection<int, AiPersonaAgent>
     */
    public function sharedPersonasForUser(User $user): Collection
    {
        return $this->sharedResources($user, AiResourceShare::RESOURCE_PERSONA, AiPersonaAgent::class);
    }

    /**
     * @param  class-string  $modelClass
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function sharedResources(User $user, string $resourceType, string $modelClass): Collection
    {
        $aud = $this->audiencesForUser($user);
        if (empty($aud['workspace']) && empty($aud['badge'])) {
            return collect();
        }

        $shares = AiResourceShare::query()
            ->where('resource_type', $resourceType)
            ->where('owner_user_id', '!=', $user->id)
            ->where(fn ($q) => $this->scopeToAudiences($q, $aud))
            ->get();

        $shares = $this->filterByOwnerAudience($shares);

        if ($shares->isEmpty()) {
            return collect();
        }

        // Highest access wins when a resource reaches the user via
        // several audiences (e.g. a team AND a badge).
        $accessByResource = [];
        foreach ($shares as $s) {
            $rid = (int) $s->resource_id;
            if (($accessByResource[$rid] ?? null) === AiResourceShare::ACCESS_EDIT) {
                continue;
            }
            $accessByResource[$rid] = $s->access === AiResourceShare::ACCESS_EDIT
                ? AiResourceShare::ACCESS_EDIT
                : AiResourceShare::ACCESS_USE;
        }

        $models = $modelClass::whereIn('id', array_keys($accessByResource))->get();
        foreach ($models as $m) {
            $m->setAttribute('share_access', $accessByResource[(int) $m->id] ?? AiResourceShare::ACCESS_USE);
        }
        return $models;
    }

    /** Non-personal team workspaces the user can share into. */
    public function shareableWorkspacesFor(User $user): Collection
    {
        $ids = $this->audiencesForUser($user)['workspace'];
        if (empty($ids)) {
            return collect();
        }
        return Workspace::whereIn('id', $ids)
            ->where('is_personal', false)
            ->orderBy('name')->get();
    }

    /** Account badges currently attached to the user. */
    public function shareableBadgesFor(User $user): Collection
    {
        return $user->accountBadges()->get();
    }

    /**
     * Existing shares for a resource, annotated with a transient
     * `audience_label`. Used by the owner's manage UI.
     *
     * @return Collection<int, AiResourceShare>
     */
    public function sharesForResource(string $resourceType, int $resourceId): Collection
    {
        $shares = AiResourceShare::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('audience_type')->orderBy('id')->get();

        if ($shares->isEmpty()) {
            return $shares;
        }

        $wsIds = $shares->where('audience_type', AiResourceShare::AUDIENCE_WORKSPACE)
            ->pluck('audience_id')->all();
        $badgeIds = $shares->where('audience_type', AiResourceShare::AUDIENCE_BADGE)
            ->pluck('audience_id')->all();

        $wsMap = $wsIds ? Workspace::whereIn('id', $wsIds)->pluck('name', 'id') : collect();
        $badgeMap = $badgeIds ? AccountBadge::whereIn('id', $badgeIds)->pluck('name', 'id') : collect();

        foreach ($shares as $s) {
            $label = $s->audience_type === AiResourceShare::AUDIENCE_WORKSPACE
                ? ($wsMap[$s->audience_id] ?? 'Team #' . $s->audience_id)
                : ($badgeMap[$s->audience_id] ?? 'Badge #' . $s->audience_id);
            $s->setAttribute('audience_label', $label);
        }
        return $shares;
    }

    /**
     * What AI resources are shared INTO a given workspace (by anyone),
     * annotated with `audience_label` plus a resolved `resource` model
     * and `resource_label`. Surfaced on the Team management page.
     *
     * @return array{minds: Collection, personas: Collection}
     */
    public function resourcesSharedWithWorkspace(int $workspaceId): array
    {
        $shares = AiResourceShare::query()
            ->where('audience_type', AiResourceShare::AUDIENCE_WORKSPACE)
            ->where('audience_id', $workspaceId)
            ->orderBy('resource_type')->get();

        // Hide shares whose owner has since left this workspace.
        $shares = $this->filterByOwnerAudience($shares);

        return [
            'minds'    => $this->hydrateResourceShares($shares, AiResourceShare::RESOURCE_MIND, AiMind::class),
            'personas' => $this->hydrateResourceShares($shares, AiResourceShare::RESOURCE_PERSONA, AiPersonaAgent::class),
        ];
    }

    /**
     * What AI resources are shared INTO badge groups the user currently
     * holds (by anyone other than themselves), annotated like
     * resourcesSharedWithWorkspace(). Surfaced on the Team management page
     * so a member also sees AI reaching them via their badges, not just
     * the active workspace. Owner-audience validity is enforced live, so
     * a share whose owner has lost the badge is dropped automatically.
     *
     * @return array{minds: Collection, personas: Collection}
     */
    public function resourcesSharedWithUserBadges(User $user): array
    {
        $badgeIds = $this->audiencesForUser($user)['badge'];
        if (empty($badgeIds)) {
            return ['minds' => collect(), 'personas' => collect()];
        }

        $shares = AiResourceShare::query()
            ->where('audience_type', AiResourceShare::AUDIENCE_BADGE)
            ->whereIn('audience_id', $badgeIds)
            ->where('owner_user_id', '!=', $user->id)
            ->orderBy('resource_type')->get();

        // Hide shares whose owner has since lost the badge.
        $shares = $this->filterByOwnerAudience($shares);

        $badgeMap = $shares->isEmpty()
            ? collect()
            : AccountBadge::whereIn('id', $shares->pluck('audience_id')->unique()->all())
                ->pluck('name', 'id');

        $minds    = $this->hydrateResourceShares($shares, AiResourceShare::RESOURCE_MIND, AiMind::class);
        $personas = $this->hydrateResourceShares($shares, AiResourceShare::RESOURCE_PERSONA, AiPersonaAgent::class);

        foreach ($minds->concat($personas) as $s) {
            $s->setAttribute('audience_label', $badgeMap[$s->audience_id] ?? ('Badge #' . $s->audience_id));
        }

        return ['minds' => $minds, 'personas' => $personas];
    }

    /**
     * @param  Collection<int, AiResourceShare>  $shares
     * @param  class-string  $modelClass
     * @return Collection<int, AiResourceShare>
     */
    protected function hydrateResourceShares(Collection $shares, string $resourceType, string $modelClass): Collection
    {
        $subset = $shares->where('resource_type', $resourceType)->values();
        if ($subset->isEmpty()) {
            return $subset;
        }
        $models = $modelClass::whereIn('id', $subset->pluck('resource_id')->all())
            ->get()->keyBy('id');
        // Resolve owner names in one go.
        $ownerNames = User::whereIn('id', $subset->pluck('owner_user_id')->all())
            ->pluck('name', 'id');
        foreach ($subset as $s) {
            $model = $models->get((int) $s->resource_id);
            $s->setAttribute('resource_model', $model);
            $s->setAttribute('resource_label', $model->name ?? ('#' . $s->resource_id));
            $s->setAttribute('owner_name', $ownerNames[$s->owner_user_id] ?? 'Unknown');
        }
        // Drop shares whose underlying resource no longer exists.
        return $subset->filter(fn ($s) => $s->getAttribute('resource_model') !== null)->values();
    }

    /**
     * Create or update a share. Validates that the sharer actually
     * belongs to the target audience.
     *
     * @throws \InvalidArgumentException on a target the user can't share into.
     */
    public function share(
        User $owner,
        string $resourceType,
        int $resourceId,
        string $audienceType,
        int $audienceId,
        string $access
    ): AiResourceShare {
        $access = $access === AiResourceShare::ACCESS_EDIT
            ? AiResourceShare::ACCESS_EDIT
            : AiResourceShare::ACCESS_USE;

        $aud = $this->audiencesForUser($owner);

        if ($audienceType === AiResourceShare::AUDIENCE_WORKSPACE) {
            if (!in_array($audienceId, $aud['workspace'], true)) {
                throw new \InvalidArgumentException('You can only share into a team you belong to.');
            }
            $ws = Workspace::find($audienceId);
            if (!$ws || $ws->is_personal) {
                throw new \InvalidArgumentException('Pick a team workspace to share with.');
            }
        } elseif ($audienceType === AiResourceShare::AUDIENCE_BADGE) {
            if (!in_array($audienceId, $aud['badge'], true)) {
                throw new \InvalidArgumentException('You can only share into a badge group you hold.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown share audience.');
        }

        return AiResourceShare::updateOrCreate(
            [
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'audience_type' => $audienceType,
                'audience_id'   => $audienceId,
            ],
            [
                'owner_user_id' => $owner->id,
                'access'        => $access,
            ]
        );
    }

    /** Remove every share for a deleted resource. Called from model hooks. */
    public static function purgeForResource(string $resourceType, int $resourceId): void
    {
        AiResourceShare::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->delete();
    }

    /** Remove every share pointing at a deleted audience. Called from model hooks. */
    public static function purgeForAudience(string $audienceType, int $audienceId): void
    {
        AiResourceShare::where('audience_type', $audienceType)
            ->where('audience_id', $audienceId)
            ->delete();
    }

    /**
     * Drop shares whose OWNER no longer belongs to the audience they
     * shared into. A creator may only share into teams they belong to
     * and badge groups they hold, so if they later leave the team or
     * lose the badge the share is no longer authoritative and access is
     * revoked for everyone — resolved live, no per-row cleanup needed.
     *
     * @param  Collection<int, AiResourceShare>  $shares
     * @return Collection<int, AiResourceShare>
     */
    protected function filterByOwnerAudience(Collection $shares): Collection
    {
        if ($shares->isEmpty()) {
            return $shares;
        }

        $ownerIds = $shares->pluck('owner_user_id')
            ->map(fn ($i) => (int) $i)->unique()->all();
        $owners = User::whereIn('id', $ownerIds)->get()->keyBy('id');

        $audByOwner = [];
        foreach ($owners as $id => $owner) {
            $audByOwner[(int) $id] = $this->audiencesForUser($owner);
        }

        return $shares->filter(function ($s) use ($audByOwner) {
            $aud = $audByOwner[(int) $s->owner_user_id] ?? null;
            if ($aud === null) {
                return false; // owner no longer exists
            }
            if ($s->audience_type === AiResourceShare::AUDIENCE_WORKSPACE) {
                return in_array((int) $s->audience_id, $aud['workspace'], true);
            }
            return in_array((int) $s->audience_id, $aud['badge'], true);
        })->values();
    }

    /**
     * Constrain a query to rows whose audience is one the user belongs to.
     *
     * @param  array{workspace:int[], badge:int[]}  $aud
     */
    protected function scopeToAudiences($query, array $aud): void
    {
        if (!empty($aud['workspace'])) {
            $query->orWhere(function ($q) use ($aud) {
                $q->where('audience_type', AiResourceShare::AUDIENCE_WORKSPACE)
                  ->whereIn('audience_id', $aud['workspace']);
            });
        }
        if (!empty($aud['badge'])) {
            $query->orWhere(function ($q) use ($aud) {
                $q->where('audience_type', AiResourceShare::AUDIENCE_BADGE)
                  ->whereIn('audience_id', $aud['badge']);
            });
        }
    }
}
