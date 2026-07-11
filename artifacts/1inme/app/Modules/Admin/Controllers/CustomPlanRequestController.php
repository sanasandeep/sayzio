<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CustomPlanRequest;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomPlanRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomPlanRequest::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('company', 'ilike', "%{$s}%");
            });
        }

        $requests = $query->paginate(20)->withQueryString();
        $statuses  = CustomPlanRequest::$statuses;
        $newCount  = CustomPlanRequest::where('status', 'new')->count();

        return view('admin.custom-plan-requests.index', compact('requests', 'statuses', 'newCount'));
    }

    public function show(CustomPlanRequest $customPlanRequest)
    {
        $customPlanRequest->load(['user', 'provisionedPlan', 'invoice', 'handledBy']);
        $plans    = Plan::active()->ordered()->get();
        $statuses = CustomPlanRequest::$statuses;

        return view('admin.custom-plan-requests.show', compact('customPlanRequest', 'plans', 'statuses'));
    }

    /**
     * Move a request to "reviewing" so it's flagged as in-progress.
     */
    public function markReviewing(CustomPlanRequest $customPlanRequest)
    {
        if ($customPlanRequest->status !== 'new') {
            return back()->with('error', 'Only new requests can be moved to reviewing.');
        }
        $customPlanRequest->update(['status' => 'reviewing']);
        return back()->with('success', 'Request marked as reviewing.');
    }

    /**
     * Approve: provision a per-user internal plan, record offer on request,
     * and notify the assigned user. Invoice is created later via normal checkout.
     */
    public function approve(Request $request, CustomPlanRequest $customPlanRequest, PlanWriter $writer)
    {
        if (!in_array($customPlanRequest->status, ['new', 'reviewing'], true)) {
            return back()->with('error', 'Only open requests can be approved.');
        }

        $data = $request->validate([
            'assigned_email'   => 'required|email|max:255',
            'offer_cycle'      => 'required|in:monthly,annual',
            'monthly_price'    => 'nullable|integer|min:0',
            'annual_price'     => 'nullable|integer|min:0',
            'currency'         => 'required|in:USD,INR',
            'base_plan_id'     => 'nullable|exists:plans,id',
            'plan_name'        => 'required|string|max:255',
            'admin_notes'      => 'nullable|string|max:2000',

            'features'                       => 'nullable|array',
            'features.max_links'             => 'nullable|integer|min:-1',
            'features.max_biolinks'          => 'nullable|integer|min:-1',
            'features.max_custom_domains'    => 'nullable|integer|min:-1',
            'features.storage_limit_mb'      => 'nullable|integer|min:-1',
            'features.max_file_size_mb'      => 'nullable|integer|min:-1',
            'features.max_workspaces'        => 'nullable|integer|min:-1',
            'features.max_forms'             => 'nullable|integer|min:-1',
            'features.analytics'             => 'nullable|in:basic,advanced',
            'features.api_calls_monthly'     => 'nullable|integer|min:-1',
            'features.stats_retention_days'  => 'nullable|integer|min:-1',
        ]);

        $admin         = Auth::guard('admin')->user();
        $assignedEmail = strtolower(trim($data['assigned_email']));
        $cycle         = $data['offer_cycle'];
        $currency      = $data['currency'];

        $monthlyMinor = (int) ($data['monthly_price'] ?? 0);
        $annualMinor  = (int) ($data['annual_price'] ?? 0);

        if ($monthlyMinor > 0 && $annualMinor === 0) {
            $annualMinor = $monthlyMinor * 10;
        } elseif ($annualMinor > 0 && $monthlyMinor === 0) {
            $monthlyMinor = (int) round($annualMinor / 10);
        }

        DB::transaction(function () use (
            $customPlanRequest, $data, $writer, $admin,
            $assignedEmail, $cycle, $currency, $monthlyMinor, $annualMinor
        ) {
            // Build features: start from base plan (if chosen), then apply overrides.
            $baseFeatures = [];
            if (!empty($data['base_plan_id'])) {
                $basePlan     = Plan::find($data['base_plan_id']);
                $baseFeatures = $basePlan?->features ?? [];
            }
            $overrides = $data['features'] ?? [];
            $features  = array_merge(
                $baseFeatures,
                array_filter($overrides, fn($v) => $v !== null && $v !== '')
            );

            // Provision a new active internal plan with the negotiated settings.
            $planName = trim($data['plan_name']);
            $slug     = $writer->uniqueSlug($planName . '-custom-' . now()->format('ymdHi'));

            $monthlyDollar = $currency === 'USD' ? $monthlyMinor / 100 : 0.0;
            $annualDollar  = $currency === 'USD' ? $annualMinor / 100  : 0.0;

            $plan = Plan::create([
                'name'                    => $planName,
                'slug'                    => $slug,
                'description'             => 'Custom plan for ' . $assignedEmail,
                'status'                  => 'active',
                'is_internal'             => true,
                'is_default'              => false,
                'is_popular'              => false,
                'is_archived'             => false,
                'sort_order'              => 999,
                'monthly_price'           => $monthlyDollar,
                'annual_price'            => $annualDollar,
                'monthly_price_secondary' => $currency === 'INR' ? $monthlyMinor / 100 : 0.0,
                'annual_price_secondary'  => $currency === 'INR' ? $annualMinor / 100  : 0.0,
                'trial_days'              => 0,
                'grace_days'              => 7,
                'refund_window_days'      => 7,
                'features'                => $features,
            ]);

            // Sync the authoritative price rows in the `prices` table.
            if ($currency === 'USD') {
                $writer->syncPriceTable($plan, [
                    'monthly_price'           => $monthlyMinor,
                    'annual_price'            => $annualMinor,
                    'monthly_price_secondary' => 0,
                    'annual_price_secondary'  => 0,
                ]);
            } else {
                $writer->syncPriceTable($plan, [
                    'monthly_price'           => 0,
                    'annual_price'            => 0,
                    'monthly_price_secondary' => $monthlyMinor,
                    'annual_price_secondary'  => $annualMinor,
                ]);
            }

            // Update the request row.
            $customPlanRequest->update([
                'status'              => 'approved',
                'assigned_email'      => $assignedEmail,
                'offer_cycle'         => $cycle,
                'provisioned_plan_id' => $plan->id,
                'admin_notes'         => $data['admin_notes'] ?? $customPlanRequest->admin_notes,
                'handled_by'          => $admin?->id,
                'handled_at'          => now(),
            ]);

            // Notify the assigned user.
            $this->notifyApproval($customPlanRequest, $plan, $assignedEmail, $cycle, $currency, $monthlyMinor, $annualMinor);
        });

        return redirect()->route('admin.custom-plan-requests.show', $customPlanRequest)
            ->with('success', 'Request approved! The custom plan has been provisioned and the user notified.');
    }

    /**
     * Decline a request with an optional reason and send a notification email.
     */
    public function decline(Request $request, CustomPlanRequest $customPlanRequest)
    {
        if (!in_array($customPlanRequest->status, ['new', 'reviewing', 'approved'], true)) {
            return back()->with('error', 'This request cannot be declined in its current state.');
        }

        $data = $request->validate([
            'decline_reason' => 'nullable|string|max:1000',
        ]);

        $admin = Auth::guard('admin')->user();

        $customPlanRequest->update([
            'status'         => 'declined',
            'decline_reason' => $data['decline_reason'] ?? null,
            'handled_by'     => $admin?->id,
            'handled_at'     => now(),
        ]);

        try {
            $reason = trim((string) ($data['decline_reason'] ?? ''));
            Emailer::send('billing.custom_plan_declined', $customPlanRequest->email, [
                'name'           => $customPlanRequest->name,
                'decline_reason' => $reason !== '' ? "\n\nReason: {$reason}" : '',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Custom plan decline email failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.custom-plan-requests.index')
            ->with('success', 'Request declined and requester notified.');
    }

    /**
     * Update admin notes on a request without changing its status.
     */
    public function updateNotes(Request $request, CustomPlanRequest $customPlanRequest)
    {
        $data = $request->validate(['admin_notes' => 'nullable|string|max:2000']);
        $customPlanRequest->update(['admin_notes' => $data['admin_notes']]);
        return back()->with('success', 'Notes saved.');
    }

    private function notifyApproval(
        CustomPlanRequest $req,
        Plan $plan,
        string $email,
        string $cycle,
        string $currency,
        int $monthlyMinor,
        int $annualMinor
    ): void {
        $priceMinor     = $cycle === 'annual' ? $annualMinor : $monthlyMinor;
        $priceFormatted = ($currency === 'INR' ? '₹' : '$') . number_format($priceMinor / 100, 2);

        try {
            Emailer::send('billing.custom_plan_approved', $email, [
                'name'      => $req->name,
                'plan_name' => $plan->name,
                'price'     => $priceFormatted,
                'cycle'     => $cycle,
                'dashboard' => url('/user/dashboard'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Custom plan approval email failed: ' . $e->getMessage());
        }

        // In-app notification for existing users.
        $assignedUser = User::whereRaw('lower(email) = ?', [strtolower($email)])->first();
        if ($assignedUser) {
            try {
                app(NotificationService::class)->notify($assignedUser, 'custom_plan_offer', [
                    'title'   => 'Your custom plan offer is ready',
                    'message' => "Your custom plan \"{$plan->name}\" has been approved - {$priceFormatted}/{$cycle}. Sign in to review and activate.",
                    'url'     => '/user/settings/billing',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Custom plan in-app notification failed: ' . $e->getMessage());
            }
        }
    }
}
