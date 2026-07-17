<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPhone extends Model
{
    protected $fillable = ['contact_id', 'label', 'value', 'value_e164', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function contact() { return $this->belongsTo(Contact::class); }

    protected static function booted(): void
    {
        // Phone changes drive duplicate matching — invalidate the owner's
        // cached duplicate-group count on any row change so the banner on the
        // contacts index stays accurate without a manual refresh.
        $flush = function (ContactPhone $phone): void {
            $userId = Contact::withoutGlobalScope('workspace')
                ->whereKey($phone->contact_id)
                ->value('user_id');
            if ($userId) {
                \App\Modules\User\Services\Contacts\ContactDuplicateDetector::flushCountCache((int) $userId);
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    /** Normalise to a canonical lookup form. Mirrors LinkedIdentifier::normalize('phone'). */
    public static function normalize(string $raw): string
    {
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', trim($raw));
        return $cleaned ?? trim($raw);
    }
}
