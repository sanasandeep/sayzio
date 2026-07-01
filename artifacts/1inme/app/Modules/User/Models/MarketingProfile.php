<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #3281 — a reusable per-creator Marketing Profile.
 *
 * Captures the durable intake (target audience, expectations, hard
 * constraints) that pre-fills every new Marketing Strategy so the creator
 * doesn't re-type the same context each time. Owned by a user (the
 * workspace owner when run inside a team workspace); one profile per
 * (user, workspace).
 */
class MarketingProfile extends Model
{
    protected $table = 'marketing_profiles';

    protected $fillable = [
        'user_id', 'workspace_id', 'target_audience', 'expectations', 'constraints',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'expectations'    => 'array',
        'constraints'     => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the single profile for an owner (workspace owner when inside a
     * team workspace). Returns null when the creator has not filled one yet.
     */
    public static function forOwner(int $userId, ?int $workspaceId = null): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    /**
     * A compact, PII-free snapshot stored on a strategy so a plan records the
     * profile it was built against. Empty when nothing meaningful is set.
     *
     * @return array<string,mixed>
     */
    public function toSnapshot(): array
    {
        if (!$this->isFilled()) {
            return [];
        }
        return array_filter([
            'target_audience' => is_array($this->target_audience) ? $this->target_audience : [],
            'expectations'    => is_array($this->expectations) ? $this->expectations : [],
            'constraints'     => is_array($this->constraints) ? $this->constraints : [],
        ], static fn ($v) => !empty($v));
    }

    /** True when the profile carries at least one meaningful value. */
    public function isFilled(): bool
    {
        foreach ([$this->target_audience, $this->expectations, $this->constraints] as $bag) {
            if (is_array($bag)) {
                foreach ($bag as $v) {
                    if (is_array($v) ? !empty($v) : trim((string) $v) !== '') {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
