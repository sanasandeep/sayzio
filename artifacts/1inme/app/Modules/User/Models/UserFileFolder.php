<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single-level folder in the user's Sayzio Files vault. User-scoped
 * (not workspace-scoped) — mirrors the vault's flat file list. Deleting
 * a folder returns its files to the root via the FK's nullOnDelete.
 */
class UserFileFolder extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function files()
    {
        return $this->hasMany(UserFile::class, 'folder_id');
    }
}
