<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single entry in the admin-managed banned-names list. Names here can't
 * be claimed as a user handle or as a link alias (regular, file, ICS or
 * VCF). Matching is case-insensitive, enforced both at the application
 * layer (BannedNameChecker) and at the database layer (functional unique
 * index on LOWER(name) — see the migration).
 */
class BannedName extends Model
{
    protected $fillable = ['name', 'note', 'created_by', 'force_rename_on_login'];

    protected $casts = [
        'force_rename_on_login' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function acknowledgements()
    {
        return $this->hasMany(BannedNameAcknowledgement::class);
    }
}
