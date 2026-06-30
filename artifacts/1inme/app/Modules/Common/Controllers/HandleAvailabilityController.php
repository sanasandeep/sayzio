<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Support\HandleAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, anonymous, rate-limited "is this @handle free?" endpoint backing
 * the homepage "claim your link" hero control. Lets a visitor see whether
 * their chosen handle is available as they type, before they ever register.
 *
 * The endpoint is intentionally unauthenticated (it fires from anonymous
 * landing-page visits) and rate-limited at the route. The verdict mirrors the
 * exact rules enforced when a handle is reserved (format, length, banned-names
 * list, uniqueness) via HandleAvailability. Whether a handle exists is already
 * public through the /@handle creator pages, so this leaks no new enumeration
 * surface beyond what is already discoverable.
 */
class HandleAvailabilityController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $handle = (string) $request->query('handle', (string) $request->input('handle', ''));

        // Hard cap the inspected length so a hostile caller can't push huge
        // strings through the validator / suggestion builder.
        $handle = mb_substr($handle, 0, 64);

        return response()->json(HandleAvailability::check($handle));
    }
}
