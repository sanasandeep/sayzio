<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileVerificationRequest extends Model
{
    protected $table = 'profile_verification_requests';

    protected $fillable = [
        'user_id', 'tick_type_id', 'official_name', 'purpose',
        'logo_path', 'proof_files', 'status', 'kind',
        'admin_notes', 'prev_verified_name', 'new_name', 'new_avatar',
        'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'proof_files' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tickType()
    {
        return $this->belongsTo(VerificationTickType::class, 'tick_type_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool   { return $this->status === 'pending';   }
    public function isApproved(): bool  { return $this->status === 'approved';  }
    public function isRejected(): bool  { return $this->status === 'rejected';  }
    public function isReverification(): bool { return $this->kind === 'reverification'; }
}
