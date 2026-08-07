<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #6737 — a saved Marketing Plan Calculator plan.
 *
 * Interactive replacement for the "Sayzio-Powered Digital Marketing Plan"
 * spreadsheet: budget, seasonality, per-channel benchmarks and the ROI /
 * value-of-Sayzio assumptions all live in one JSON `payload`. The browser
 * does every computation live; the server just stores the inputs.
 */
class MarketingPlanCalc extends Model
{
    protected $table = 'marketing_plan_calcs';

    protected $fillable = ['user_id', 'workspace_id', 'name', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All of an owner's saved plans, newest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,self>
     */
    public static function listForOwner(int $userId, ?int $workspaceId = null)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('id')
            ->get();
    }
}
