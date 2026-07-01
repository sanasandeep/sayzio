<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #3281 / #3302 — a reusable per-creator Marketing "project" profile.
 *
 * Captures the durable intake (name, business, industry, brand kit, main
 * offer, target audience, expectations, constraints, budget, currency) that
 * pre-fills a new Marketing Strategy so the creator doesn't re-type the same
 * context each time. Owned by a user (the workspace owner when run inside a
 * team workspace); a creator can now keep MULTIPLE named projects.
 */
class MarketingProfile extends Model
{
    protected $table = 'marketing_profiles';

    protected $fillable = [
        'user_id', 'workspace_id', 'name', 'business_name', 'industry',
        'brand_kit_id', 'main_offer', 'target_audience', 'expectations',
        'constraints', 'budget', 'currency',
    ];

    protected $casts = [
        'brand_kit_id'    => 'integer',
        'target_audience' => 'array',
        'expectations'    => 'array',
        'constraints'     => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The Brand Kit this project brands its reports with (optional). */
    public function brandKit()
    {
        return $this->belongsTo(BrandKit::class, 'brand_kit_id');
    }

    /**
     * Resolve the DEFAULT (first / legacy) profile for an owner. Kept for
     * backward compatibility — callers that pre-date multi-project use this to
     * get "the" profile.  Returns null when the creator has none yet.
     */
    public static function forOwner(int $userId, ?int $workspaceId = null): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->first();
    }

    /**
     * All of an owner's project profiles, newest-meaningful first (by id asc
     * so the original default leads). Used by the project picker + manager.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,self>
     */
    public static function listForOwner(int $userId, ?int $workspaceId = null)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get();
    }

    /** A human label for the project — its name, else business, else a stub. */
    public function displayName(): string
    {
        foreach ([$this->name, $this->business_name] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return 'Untitled project';
    }

    /**
     * A compact, PII-free snapshot stored on a strategy so a plan records the
     * project it was built against. Empty when nothing meaningful is set.
     *
     * @return array<string,mixed>
     */
    public function toSnapshot(): array
    {
        if (!$this->isFilled()) {
            return [];
        }
        return array_filter([
            'name'            => trim((string) $this->name),
            'business_name'   => trim((string) $this->business_name),
            'industry'        => trim((string) $this->industry),
            'brand_kit_id'    => $this->brand_kit_id ? (int) $this->brand_kit_id : null,
            'main_offer'      => trim((string) $this->main_offer),
            'budget'          => trim((string) $this->budget),
            'currency'        => trim((string) $this->currency),
            'target_audience' => is_array($this->target_audience) ? $this->target_audience : [],
            'expectations'    => is_array($this->expectations) ? $this->expectations : [],
            'constraints'     => is_array($this->constraints) ? $this->constraints : [],
        ], static fn ($v) => !empty($v));
    }

    /** True when the profile carries at least one meaningful value. */
    public function isFilled(): bool
    {
        foreach ([$this->name, $this->business_name, $this->industry, $this->main_offer, $this->budget] as $scalar) {
            if (trim((string) $scalar) !== '') {
                return true;
            }
        }
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
