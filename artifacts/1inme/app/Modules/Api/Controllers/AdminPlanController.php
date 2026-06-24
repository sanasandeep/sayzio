<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Bearer-token parity for the back-office plan editor: list plans (with the
 * admin-only `is_internal` flag), create / update a plan (including
 * setting/clearing `is_internal`) and the deep-copy Duplicate action.
 *
 * Authority comes from the operator's email-linked back-office Admin record
 * ({@see User::adminAccount()}), exactly like {@see AdminAccessController} and
 * the web routes — the same admin-guard `plans.view` / `plans.manage`
 * permissions gate each action. All write logic is shared with the web
 * controller through {@see PlanWriter} so the two surfaces never diverge.
 */
class AdminPlanController extends Controller
{
    use ApiResponses;

    public function __construct(private PlanWriter $writer)
    {
    }

    /**
     * Admin plan listing (active lineup + archived), each row including
     * `is_internal` so the mobile editor can show/toggle the flag. Unlike
     * the public `/plans` catalog this is NOT filtered by `->public()` —
     * internal plans must stay visible to admins. Gated behind `plans.view`.
     */
    public function index(Request $request)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('plans.view')) {
            return $this->forbidden('You are not allowed to view plans.');
        }

        $plans = Plan::withCount('users')->ordered()->orderBy('id')->get();

        return $this->ok([
            'plans' => $plans->map(fn (Plan $p) => $this->serialize($p))->all(),
        ]);
    }

    /**
     * Create a plan, honouring `is_internal`. Mirrors the web store().
     * Gated behind `plans.manage`.
     */
    public function store(Request $request)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('plans.manage')) {
            return $this->forbidden('You are not allowed to manage plans.');
        }

        $plan = $this->writer->createFromRequest($request);

        return $this->created($this->serialize($plan->loadCount('users')));
    }

    /**
     * Update a plan, honouring `is_internal`. Mirrors the web update().
     * Gated behind `plans.manage`.
     */
    public function update(Request $request, int $planId)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('plans.manage')) {
            return $this->forbidden('You are not allowed to manage plans.');
        }

        $plan = Plan::find($planId);
        if (! $plan) {
            return $this->notFound('Plan not found.');
        }

        $plan = $this->writer->updateFromRequest($request, $plan);

        return $this->ok($this->serialize($plan->loadCount('users')));
    }

    /**
     * Deep-copy a plan (features + polymorphic price rows + addons). The
     * copy is forced internal + inactive. Mirrors the web duplicate().
     * Gated behind `plans.manage`.
     */
    public function duplicate(Request $request, int $planId)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('plans.manage')) {
            return $this->forbidden('You are not allowed to manage plans.');
        }

        $plan = Plan::find($planId);
        if (! $plan) {
            return $this->notFound('Plan not found.');
        }

        $copy = $this->writer->duplicate($plan);

        return $this->created($this->serialize($copy->loadCount('users')));
    }

    /**
     * Flat admin-facing plan shape. Includes the admin-only `is_internal`
     * flag plus the identity / pricing / lifecycle fields the editor needs.
     */
    private function serialize(Plan $plan): array
    {
        return [
            'id'                       => $plan->id,
            'name'                     => $plan->name,
            'slug'                     => $plan->slug,
            'description'              => $plan->description,
            'status'                   => $plan->status,
            'is_default'               => (bool) $plan->is_default,
            'is_popular'               => (bool) $plan->is_popular,
            'is_archived'              => (bool) $plan->is_archived,
            'is_internal'              => (bool) $plan->is_internal,
            'sort_order'               => (int) ($plan->sort_order ?? 0),
            'trial_days'               => (int) ($plan->trial_days ?? 0),
            'grace_days'               => (int) ($plan->grace_days ?? 0),
            'refund_window_days'       => (int) ($plan->refund_window_days ?? 0),
            'monthly_price'            => $plan->monthly_price,
            'annual_price'             => $plan->annual_price,
            'monthly_price_secondary'  => $plan->monthly_price_secondary,
            'annual_price_secondary'   => $plan->annual_price_secondary,
            'features'                 => is_array($plan->features) ? $plan->features : [],
            'users_count'              => (int) ($plan->users_count ?? 0),
        ];
    }

    /**
     * The signed-in user's active back-office Admin record, or null.
     */
    private function activeAdmin(Request $request): ?Admin
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        $admin = $user->adminAccount();
        return ($admin && $admin->status === 'active') ? $admin : null;
    }
}
