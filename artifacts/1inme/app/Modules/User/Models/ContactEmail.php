<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    protected $fillable = ['contact_id', 'label', 'value', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function contact() { return $this->belongsTo(Contact::class); }

    public static function normalize(string $raw): string
    {
        return strtolower(trim($raw));
    }
}
