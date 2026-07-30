<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One visitor submission collected through a Buzz collector/feedback
 * notification (email signup, SMS collector, survey answer, star rating…).
 * Shared store across all collector-style templates (task #6179).
 */
class SocialProofSubmission extends Model
{
    protected $fillable = [
        'social_proof_id', 'notification_id', 'type',
        'name', 'email', 'phone', 'message', 'answer', 'rating',
        'page_url', 'ip', 'is_spam',
    ];

    protected $casts = [
        'rating'  => 'integer',
        'is_spam' => 'boolean',
    ];

    public function socialProof()
    {
        return $this->belongsTo(SocialProof::class);
    }

    /** Human summary of the captured value, for lists + CSV. */
    public function valueSummary(): string
    {
        $parts = array_filter([
            $this->name,
            $this->email,
            $this->phone,
            $this->rating !== null ? 'rating: ' . $this->rating : null,
            $this->answer,
            $this->message,
        ], fn ($v) => $v !== null && $v !== '');
        return implode(' · ', $parts);
    }
}
