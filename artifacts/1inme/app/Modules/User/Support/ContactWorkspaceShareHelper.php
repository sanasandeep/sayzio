<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactWorkspaceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Helpers for workspace contact sharing — extracting from controllers so the
 * logic can be reused in both the web and API layers without duplication.
 */
class ContactWorkspaceShareHelper
{
    /**
     * Returns the IDs of contacts that are shared with the given workspace,
     * along with the sharing user_id. Keyed by contact_id.
     * The result is a Collection of ContactWorkspaceShare models with their
     * sharedBy user eager-loaded.
     *
     * @return Collection<int,ContactWorkspaceShare>
     */
    public static function sharesForWorkspace(int $workspaceId): Collection
    {
        return ContactWorkspaceShare::with('sharedBy')
            ->where('workspace_id', $workspaceId)
            ->get()
            ->keyBy('contact_id');
    }

    /**
     * Returns contacts shared with the given workspace that are NOT owned by
     * the given user (i.e. contacts shared by other members).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Contact>
     */
    public static function contactsSharedToWorkspace(
        int $workspaceId,
        int $excludeUserId,
        string $q = '',
        string $tab = 'all'
    ): \Illuminate\Database\Eloquent\Collection {
        $sharedContactIds = ContactWorkspaceShare::where('workspace_id', $workspaceId)
            ->pluck('contact_id');

        if ($sharedContactIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $query = Contact::withoutGlobalScope('workspace')
            ->whereIn('id', $sharedContactIds)
            ->where('user_id', '!=', $excludeUserId)
            ->with(['phones', 'emails', 'biolinkUser',
                'workspaceShares' => fn ($q) => $q->where('workspace_id', $workspaceId)->with('sharedBy'),
            ]);

        if ($tab === 'biolink') {
            $query->whereNotNull('biolink_user_id');
        }

        if ($q !== '') {
            $needle = '%' . $q . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name', 'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization', 'ilike', $needle)
                  ->orWhereHas('phones', fn ($q2) => $q2->where('value', 'ilike', $needle))
                  ->orWhereHas('emails', fn ($q2) => $q2->where('value', 'ilike', $needle));
            });
        }

        return $query->orderBy('display_name')->limit(200)->get();
    }

    /**
     * Returns the ContactWorkspaceShare for a contact+workspace pair if it
     * exists, or null. Used for authorization checks.
     */
    public static function findShare(int $contactId, int $workspaceId): ?ContactWorkspaceShare
    {
        return ContactWorkspaceShare::where('contact_id', $contactId)
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    /**
     * True if the given user can view a contact that is shared with a workspace.
     * The user must be the workspace owner or an active member with 'view' permission.
     */
    public static function userCanViewShared(User $user, Workspace $workspace): bool
    {
        if ((int) $workspace->owner_user_id === (int) $user->id) return true;
        return $user->canInWorkspace($workspace, 'view');
    }

    /**
     * True if the given user can edit a contact that is shared with a workspace.
     * The user must be the workspace owner or an active member with 'edit' permission.
     */
    public static function userCanEditShared(User $user, Workspace $workspace): bool
    {
        if ((int) $workspace->owner_user_id === (int) $user->id) return true;
        return $user->canInWorkspace($workspace, 'edit');
    }

    /**
     * True if the given user can unshare or delete a contact from a workspace.
     * Only the contact owner or workspace owner can do this.
     */
    public static function userCanManageShare(User $user, Contact $contact, Workspace $workspace): bool
    {
        if ((int) $contact->user_id === (int) $user->id) return true;
        if ((int) $workspace->owner_user_id === (int) $user->id) return true;
        return false;
    }

    /**
     * Share a contact with a workspace. Returns the share record (existing or
     * freshly created). Does NOT check authorization — callers must gate first.
     */
    public static function share(Contact $contact, Workspace $workspace, User $sharedBy): ContactWorkspaceShare
    {
        return ContactWorkspaceShare::firstOrCreate(
            ['contact_id' => $contact->id, 'workspace_id' => $workspace->id],
            ['shared_by_user_id' => $sharedBy->id]
        );
    }

    /**
     * Unshare a contact from a workspace. Returns true if a share was removed.
     * Does NOT check authorization — callers must gate first.
     */
    public static function unshare(Contact $contact, int $workspaceId): bool
    {
        $deleted = ContactWorkspaceShare::where('contact_id', $contact->id)
            ->where('workspace_id', $workspaceId)
            ->delete();
        return $deleted > 0;
    }
}
