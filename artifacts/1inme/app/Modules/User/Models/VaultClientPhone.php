<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class VaultClientPhone extends Model
{
    use BelongsToWorkspace;

    protected $table = 'vault_client_phones';
    protected $fillable = ['workspace_id', 'client_id', 'phone', 'label', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];
}
