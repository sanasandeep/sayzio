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
        'notes', 'photo_path', 'photo_url',
        'biolink_user_id', 'biolink_attached_at', 'detached_biolink_user_ids',
        'last_synced_at', 'locally_modified_at',
    ];

    protected function casts(): array
    {
        return [
            'detached_biolink_user_ids' => 'array',
            'biolink_attached_at'       => 'datetime',
            'last_synced_at'            => 'datetime',
            'locally_modified_at'       => 'datetime',
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
