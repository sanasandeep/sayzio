<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\User\Concerns\HasWorkspaceEncryptedFields;
use Illuminate\Database\Eloquent\Model;

class VaultCredential extends Model
{
    use BelongsToWorkspace, HasWorkspaceEncryptedFields;

    protected $table = 'vault_credentials';

    protected $fillable = [
        'workspace_id', 'created_by_user_id',
        'label', 'url', 'username', 'visibility', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    /** virtual field => db column */
    public array $encryptedFields = [
        'password'       => 'password_encrypted',
        'notes'          => 'notes_encrypted',
        'custom_fields'  => 'custom_fields_encrypted',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(VaultAttachment::class, 'parent_id', 'id')
            ->where('parent_type', 'credential');
    }

    /** Visibility check: private entries are visible only to creator + workspace owner. */
    public function visibleTo(User $user, Workspace $workspace): bool
    {
        if ($this->visibility !== 'private') return true;
        if ((int) $workspace->owner_user_id === (int) $user->id) return true;
        if ($user->hasPermission('user.vault.access_any')) return true;
        return (int) $this->created_by_user_id === (int) $user->id;
    }
}
