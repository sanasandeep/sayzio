<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'google_contacts_account_id', 'google_resource_name', 'google_etag',
        'display_name', 'given_name', 'family_name', 'organization', 'job_title',
        'website', 'socials', 'manual_profile', 'notes', 'sources', 'tags',
        'photo_path', 'photo_url',
        'biolink_user_id', 'biolink_attached_at', 'detached_biolink_user_ids',
        'last_synced_at', 'locally_modified_at',
        'follow_up_at', 'follow_up_note', 'follow_up_tz', 'follow_up_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'detached_biolink_user_ids' => 'array',
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
    protected static function booted(): void
    {
        static::created(function (Contact $contact): void {
            $contact->queueCrmPush();
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

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function biolinkUser(): BelongsTo     { return $this->belongsTo(User::class, 'biolink_user_id'); }
    public function googleAccount(): BelongsTo   { return $this->belongsTo(GoogleContactsAccount::class, 'google_contacts_account_id'); }
    public function phones(): HasMany            { return $this->hasMany(ContactPhone::class); }
    public function emails(): HasMany            { return $this->hasMany(ContactEmail::class); }

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
