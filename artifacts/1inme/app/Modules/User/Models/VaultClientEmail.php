<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class VaultClientEmail extends Model
{
    use BelongsToWorkspace;

    protected $table = 'vault_client_emails';
    protected $fillable = ['workspace_id', 'client_id', 'email', 'label', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];
}
