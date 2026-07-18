<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    protected $fillable = ['contact_id', 'label', 'value', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function contact() { return $this->belongsTo(Contact::class); }

    protected static function booted(): void
    {
        // Email changes drive duplicate matching — invalidate the owner's
        // cached duplicate-group count on any row change so the banner on the
        // contacts index stays accurate without a manual refresh.
        $flush = function (ContactEmail $email): void {
            $userId = Contact::withoutGlobalScope('workspace')
                ->whereKey($email->contact_id)
                ->value('user_id');
            if ($userId) {
                \App\Modules\User\Services\Contacts\ContactDuplicateDetector::flushCountCache((int) $userId);
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    public static function normalize(string $raw): string
    {
        return strtolower(trim($raw));
    }
}
