<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One identified incoming call logged against a contact (Dialer caller-ID).
 * Structured replacement for the old "call received" note lines, so the
 * contact profile can render a proper call-history timeline.
 */
class ContactCallLog extends Model
{
    protected $fillable = ['user_id', 'contact_id', 'number', 'direction', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
