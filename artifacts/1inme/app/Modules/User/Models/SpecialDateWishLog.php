<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #6551 — idempotency guard for the special-date wish fan-out. One row
 * per (creator, entry, occurrence-year); the unique index makes command
 * re-runs no-ops for occurrences already processed.
 */
class SpecialDateWishLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'entry_id', 'occurrence_year', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
