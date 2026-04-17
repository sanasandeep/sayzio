<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class SocialProofEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'social_proof_id', 'notification_id', 'kind', 'page_url', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function proof()
    {
        return $this->belongsTo(SocialProof::class, 'social_proof_id');
    }
}
