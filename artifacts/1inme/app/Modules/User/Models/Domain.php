<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'domain', 'type', 'is_verified', 'is_active',
        'verification_token', 'cname_target', 'verified_at',
        'dns_status', 'dns_last_checked_at', 'dns_last_target',
        'dns_drift_started_at', 'dns_drift_notified_at',
        'dns_unverified_warning_sent_at',
    ];

    public const DNS_STATUS_HEALTHY    = 'healthy';
    public const DNS_STATUS_DRIFTING   = 'drifting';
    public const DNS_STATUS_UNVERIFIED = 'unverified';

    protected function casts(): array
    {
        return [
            'is_verified'                    => 'boolean',
            'is_active'                      => 'boolean',
            'verified_at'                    => 'datetime',
            'dns_last_checked_at'            => 'datetime',
            'dns_drift_started_at'           => 'datetime',
            'dns_drift_notified_at'          => 'datetime',
            'dns_unverified_warning_sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    /**
     * Plans tagged for an admin-global domain. Ignored for user-owned domains
     * (those follow user ownership rather than plan tagging).
     */
    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'domain_plan');
    }

    /** True when this is an admin-managed domain (no owning user). */
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Domains the given user can attach links to: their own
     * verified+active domains plus admin-global active domains tagged for
     * their plan (or untagged ones, which are open to every plan).
     */
    public static function availableTo(User $user)
    {
        $planId       = $user->plan_id;
        $planFeatures = $user->plan?->features ?? [];
        $hasCustomDomainsFeature = !empty($planFeatures['custom_domains']);

        return static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($user, $planId, $hasCustomDomainsFeature) {
                // User-owned verified domains — only when the user's current
                // plan still includes the `custom_domains` feature. A
                // downgraded user keeps the rows (so an upgrade restores
                // them) but cannot attach them to new/edited links.
                if ($hasCustomDomainsFeature) {
                    $q->orWhere(function ($own) use ($user) {
                        $own->where('user_id', $user->id)->where('is_verified', true);
                    });
                }
                // Admin-global active+verified domains: untagged ones are open
                // to every plan; tagged ones must include the user's plan.
                $q->orWhere(function ($global) use ($planId) {
                    $global->whereNull('user_id')
                        ->where('is_verified', true)
                        ->where(function ($p) use ($planId) {
                            $p->whereDoesntHave('plans');
                            if ($planId) {
                                $p->orWhereHas('plans', fn ($pp) => $pp->where('plans.id', $planId));
                            }
                        });
                });
            })
            ->orderBy('domain');
    }
}
