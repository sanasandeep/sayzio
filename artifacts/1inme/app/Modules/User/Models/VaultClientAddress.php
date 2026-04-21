<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class VaultClientAddress extends Model
{
    use BelongsToWorkspace;

    protected $table = 'vault_client_addresses';
    protected $fillable = [
        'workspace_id', 'client_id', 'label',
        'line1', 'line2', 'city', 'region', 'postal_code', 'country',
    ];
}
