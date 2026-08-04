<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A completed Event Connect QR flow — one row per (event link, user).
 * `was_new_user` is stamped once at creation (the account was provisioned
 * by this flow) and never flipped by later repeat scans. Not workspace
 * scoped: rows are keyed by the event link and read through it.
 */
class EventQrConnect extends Model
{
    protected $fillable = [
        'link_id', 'user_id', 'was_new_user', 'rsvp_id', 'followed',
    ];

    protected $casts = [
        'was_new_user' => 'boolean',
        'followed'     => 'boolean',
    ];

    public function link() { return $this->belongsTo(Link::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function rsvp() { return $this->belongsTo(Rsvp::class); }
}
