<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPhone extends Model
{
    protected $fillable = ['contact_id', 'label', 'value', 'value_e164', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function contact() { return $this->belongsTo(Contact::class); }

    /** Normalise to a canonical lookup form. Mirrors LinkedIdentifier::normalize('phone'). */
    public static function normalize(string $raw): string
    {
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', trim($raw));
        return $cleaned ?? trim($raw);
    }
}
