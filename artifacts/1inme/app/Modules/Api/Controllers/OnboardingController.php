<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OnboardingController extends Controller
{
    use ApiResponses;

    public function status(Request $request)
    {
        $u = $request->user();
        return $this->ok([
            'onboarded_at'      => optional($u->onboarded_at)->toIso8601String(),
            'has_handle'        => (bool) ($u->handle ?? false),
            'email_verified'    => (bool) ($u->email_verified_at ?? false),
            'has_links'         => $u->id ? \App\Modules\User\Models\Link::where('user_id', $u->id)->exists() : false,
            'has_biolink'       => $u->id ? \App\Modules\User\Models\Link::where('user_id', $u->id)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->exists() : false,
        ]);
    }

    public function complete(Request $request)
    {
        $u = $request->user();
        $u->forceFill(['onboarded_at' => $u->onboarded_at ?? now()])->save();
        return $this->ok(['onboarded_at' => optional($u->onboarded_at)->toIso8601String()]);
    }
}
