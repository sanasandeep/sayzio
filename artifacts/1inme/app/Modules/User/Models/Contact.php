<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{

    use BelongsToWorkspace;

    /**
     * Contacts belong to the ACCOUNT, not to a workspace.
     *
     * They are owner-scoped by user_id, they are reused across an
     * owner's workspaces on purpose, and the whole product treats the
     * address book as one thing. The workspace_id column is still
     * filled in and still worth having as provenance -- where a contact
     * was captured -- but it must not decide who can read the row.
     *
     * It used to, on about forty of the sixty query sites across the two
     * ContactControllers. `index` and `show` had opted out by hand; the
     * mutations had not. So on mobile a contact saved under another
     * workspace was listed, opened, and then refused every edit: rename,
     * tag, note, follow-up and delete all 404'd, with nothing to tell
     * anybody why. Nobody decided that. Forty call sites each answered
     * the question separately and about half got it wrong.
     *
     * The hand-written withoutGlobalScope('workspace') calls dotted
     * around the contact code are now redundant. They are harmless and
     * are left alone here rather than touched in the same change as the
     * behaviour.
     *
     * `workspace_record_access` is untouched and still applies: WHICH
     * TEAM MEMBER may see which record is a separate question with a
     * real answer, and it is not this one.
     */
    public static function readsAreWorkspaceScoped(): bool
    {
        return false;
    }

    protected $fillable = [
        'user_id', 'google_contacts_account_id', 'google_resource_name', 'google_etag',
        'display_name', 'given_name', 'family_name', 'organization', 'job_title',
        'website', 'socials', 'manual_profile', 'notes', 'sources', 'tags', 'contact_type',
        'photo_path', 'photo_url',
        'biolink_user_id', 'biolink_attached_at', 'detached_biolink_user_ids',
        'last_synced_at', 'locally_modified_at',
        'follow_up_at', 'follow_up_note', 'follow_up_tz', 'follow_up_notified_at',
        'is_auto_captured',
    ];

    protected function casts(): array
    {
        return [
            'detached_biolink_user_ids' => 'array',
            'is_auto_captured'          => 'boolean',
            'sources'                   => 'array',
            'tags'                      => 'array',
            'socials'                   => 'array',
            'manual_profile'            => 'array',
            'biolink_attached_at'       => 'datetime',
            'last_synced_at'            => 'datetime',
            'locally_modified_at'       => 'datetime',
            'follow_up_at'              => 'datetime',
            'follow_up_notified_at'     => 'datetime',
        ];
    }

    /**
     * When a contact is captured in Sayzio (manual add, bulk import, Google
     * sync, biolink auto-attach), fan it out to the owner's connected CRMs.
     * Loop-safe: contacts imported *from* a CRM carry a `crm:` source, so they
     * are never echoed back out. The push job builds the lead from the fresh
     * record after commit, so emails/phones attached later are included.
     */
    /**
     * The workspace a contact belongs to when no request supplied one.
     *
     * Contacts are owner-scoped: `user_id` is the workspace owner, not the
     * team member who happened to type it in. So the answer is a workspace
     * that owner owns -- their active one when they own it, otherwise their
     * first. Read-only on purpose; see the note in booted().
     */
    protected static function ownerWorkspaceId(int $ownerId): ?int
    {
        $active = User::whereKey($ownerId)->value('active_workspace_id');

        if ($active) {
            $owned = Workspace::whereKey($active)
                ->where('owner_user_id', $ownerId)
                ->value('id');

            if ($owned) {
                return (int) $owned;
            }
        }

        $first = Workspace::where('owner_user_id', $ownerId)
            ->orderBy('id')
            ->value('id');

        return $first ? (int) $first : null;
    }

    protected static function booted(): void
    {
        // A contact must never be born without a workspace.
        //
        // BelongsToWorkspace fills workspace_id from the container-bound
        // `current_workspace`, which request middleware sets and a queue
        // worker, a webhook or an artisan command does not. Its read scope
        // then filters every lookup to `workspace_id = <active>`, so a row
        // written with NULL is invisible to the person who owns it. That is
        // not hypothetical: a CSV import once reported "completed" while
        // every contact it created silently vanished (see
        // ImportedContactsLandInTheOwnersWorkspaceTest). That job was fixed
        // by binding the workspace inside it, but CrmSyncService,
        // GoogleContactsSyncService and WhatsAppAgentTools all create
        // contacts with no binding at all, and the next writer will forget
        // too. So the floor belongs on the model.
        //
        // This runs only when nothing else supplied a workspace, so an
        // ordinary request write is untouched.
        //
        // Deliberately NOT WorkspaceContext::resolve(): it writes to the
        // session and will CREATE a workspace via ensureDefaultWorkspace().
        // Neither is acceptable from a model hook on a queue worker. This
        // mirrors resolve()'s precedence -- the owner's active workspace,
        // then their first -- as a read-only lookup, and leaves the column
        // NULL when the owner has no workspace at all, which is exactly
        // today's behaviour rather than a new one.
        static::creating(function (Contact $contact): void {
            if (!empty($contact->workspace_id) || empty($contact->user_id)) {
                return;
            }

            $contact->workspace_id = self::ownerWorkspaceId((int) $contact->user_id);
        });

        static::created(function (Contact $contact): void {
            $contact->queueCrmPush();
        });

        // A create, edit (rename etc.) or delete can change which contacts
        // count as duplicates — drop the cached duplicate-group count so the
        // contacts-index banner reflects the new state immediately.
        static::saved(function (Contact $contact): void {
            if ($contact->user_id) {
                \App\Modules\User\Services\Contacts\ContactDuplicateDetector::flushCountCache((int) $contact->user_id);
            }
        });
        static::deleted(function (Contact $contact): void {
            if ($contact->user_id) {
                \App\Modules\User\Services\Contacts\ContactDuplicateDetector::flushCountCache((int) $contact->user_id);
            }
        });

        // Keep this profile's billing on not-yet-started events in step
        // with it. An event freezes a billing to its stored columns the
        // moment it starts, so those columns have to be current right up
        // to that point or the page jumps to a stale value mid-event.
        // Started events are never touched. See
        // EventPerson::syncSnapshotsForContact().
        //
        // `updated`, not `saved`: a brand new contact cannot be billed
        // anywhere yet. This does cost one UPDATE per contact edit --
        // it matches no rows for a contact that is not on any event,
        // but the statement is still issued.
        static::updated(function (Contact $contact): void {
            \App\Modules\User\Models\EventPerson::syncSnapshotsForContact($contact);
        });
    }

    /**
     * Fan this contact out to the owner's connected CRMs. Loop-safe: contacts
     * imported *from* a CRM carry a `crm:` source, so they are never echoed
     * back out. The push job builds the lead from the fresh record after
     * commit, so emails/phones attached later are included. Cheap no-op when
     * the user has no CRM connected (guarded inside the job's forContact()).
     *
     * Shared by the `created` hook (new contacts) and the Leads approve flow
     * (leads merged/enriched into an existing contact) so both route through
     * the exact same push path.
     */
    public function queueCrmPush(): void
    {
        if (!$this->user_id) {
            return;
        }
        foreach ((array) $this->sources as $source) {
            if (is_string($source) && str_starts_with($source, 'crm:')) {
                return;
            }
        }
        \App\Jobs\PushLeadToCrmJob::forContact((int) $this->user_id, (int) $this->id);
    }

    /** @return array<string,mixed> normalized CRM lead payload. */
    public function toCrmLead(): array
    {
        $email = $this->emails()->orderByDesc('is_primary')->value('value');
        $phone = $this->phones()->orderByDesc('is_primary')->value('value');
        $display = $this->display_name ?: trim(($this->given_name ?? '') . ' ' . ($this->family_name ?? ''));

        return [
            'email'        => $email,
            'phone'        => $phone,
            'first_name'   => $this->given_name,
            'last_name'    => $this->family_name,
            'display_name' => $display !== '' ? $display : null,
            'company'      => $this->organization,
            'source'       => 'contact',
        ];
    }

    /**
     * Resolve implicit route-model bindings account-wide. The Contacts page,
     * dialer finder and API all present the owner's FULL address book (across
     * workspaces), so opening a contact saved while a different workspace was
     * active must not 404 under the `workspace` global scope. Ownership is
     * still enforced by each controller action's
     * `abort_if($contact->user_id !== workspace_owner_id(), 403)` guard.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->newQuery()
            ->withoutGlobalScope('workspace')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function biolinkUser(): BelongsTo     { return $this->belongsTo(User::class, 'biolink_user_id'); }
    public function googleAccount(): BelongsTo   { return $this->belongsTo(GoogleContactsAccount::class, 'google_contacts_account_id'); }
    public function phones(): HasMany            { return $this->hasMany(ContactPhone::class); }
    public function emails(): HasMany            { return $this->hasMany(ContactEmail::class); }
    public function workspaceShares(): HasMany   { return $this->hasMany(ContactWorkspaceShare::class); }

    /**
     * True when this contact has been shared with the given workspace.
     */
    public function isSharedWithWorkspace(int $workspaceId): bool
    {
        return $this->workspaceShares()->where('workspace_id', $workspaceId)->exists();
    }

    public function nameForDisplay(): string
    {
        if ($this->display_name) return $this->display_name;
        $n = trim(($this->given_name ?? '') . ' ' . ($this->family_name ?? ''));
        if ($n !== '') return $n;
        return $this->phones()->value('value') ?? $this->emails()->value('value') ?? '(no name)';
    }

    public function initials(): string
    {
        $name = $this->nameForDisplay();
        $parts = preg_split('/\s+/', trim($name));
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($a . $b) ?: '?';
    }

    public function photoUrl(): ?string
    {
        if ($this->photo_path) return \Storage::disk('public')->url($this->photo_path);
        return $this->photo_url;
    }
}
