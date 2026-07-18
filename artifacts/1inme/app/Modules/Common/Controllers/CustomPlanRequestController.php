<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CustomPlanRequest;
use Illuminate\Http\Request;

/**
 * Public-facing endpoint for "Request a custom plan" form submissions.
 * Accepts both anonymous and authenticated visitors.
 * Rate-limited + honeypot-protected like the existing contact form.
 */
class CustomPlanRequestController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot: website field must be blank
        if ((string) $request->input('website', '') !== '') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => true]);
            }
            return $this->successResponse($request);
        }

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|max:255',
            'company'          => 'nullable|string|max:255',
            'requirements'     => 'nullable|string|max:2000',
            'expected_volume'  => 'nullable|string|max:255',
            'budget'           => 'nullable|string|max:255',
            'preferred_cycle'  => 'nullable|in:monthly,annual,either',
            'message'          => 'nullable|string|max:3000',
            'website'          => 'nullable|max:0', // honeypot
        ]);

        $user = $request->user();

        CustomPlanRequest::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'company'         => $data['company'] ?? null,
            'requirements'    => $data['requirements'] ?? null,
            'expected_volume' => $data['expected_volume'] ?? null,
            'budget'          => $data['budget'] ?? null,
            'preferred_cycle' => $data['preferred_cycle'] ?? null,
            'message'         => $data['message'] ?? null,
            'user_id'         => $user?->id,
            'status'          => 'new',
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Request received! Our team will be in touch soon.']);
        }

        return $this->successResponse($request);
    }

    private function successResponse(Request $request)
    {
        $back = $request->input('_redirect_back', '');
        if ($back && str_starts_with($back, '/')) {
            return redirect($back)->with('custom_plan_request_sent', true);
        }
        return back()->with('custom_plan_request_sent', true);
    }
}
