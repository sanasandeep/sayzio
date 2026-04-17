<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class SocialProofItem extends Model
{
    protected $fillable = [
        'social_proof_id', 'name', 'location', 'action',
        'image_url', 'link_url', 'time_label', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function proof()
    {
        return $this->belongsTo(SocialProof::class, 'social_proof_id');
    }
}
