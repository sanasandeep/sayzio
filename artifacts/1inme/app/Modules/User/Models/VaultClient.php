<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\User\Concerns\HasWorkspaceEncryptedFields;
use Illuminate\Database\Eloquent\Model;

class VaultClient extends Model
{
    use BelongsToWorkspace, HasWorkspaceEncryptedFields;

    protected $table = 'vault_clients';

    protected $fillable = [
        'workspace_id', 'created_by_user_id',
        'name', 'company', 'website',
        'primary_email', 'primary_phone',
        'visibility', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public array $encryptedFields = [
        'notes'          => 'notes_encrypted',
        'fields'         => 'fields_encrypted',
        'social_handles' => 'social_handles_encrypted',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function emails()
    {
        return $this->hasMany(VaultClientEmail::class, 'client_id');
    }

    public function phones()
    {
        return $this->hasMany(VaultClientPhone::class, 'client_id');
    }

    public function addresses()
    {
        return $this->hasMany(VaultClientAddress::class, 'client_id');
    }

    public function attachments()
    {
        // Plain hasMany on parent_id with an explicit parent_type filter so
        // the literal 'client' / 'credential' discriminator we store matches
        // (a morphMany would inject the FQCN as the type and exclude rows).
        return $this->hasMany(VaultAttachment::class, 'parent_id', 'id')
            ->where('parent_type', 'client');
    }

    public function visibleTo(User $user, Workspace $workspace): bool
    {
        if ($this->visibility !== 'private') return true;
        if ((int) $workspace->owner_user_id === (int) $user->id) return true;
        if ($user->isSuperAdmin()) return true;
        return (int) $this->created_by_user_id === (int) $user->id;
    }
}
