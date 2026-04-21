<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerLookup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the mobile Dialer page: looks an E.164 number up against the
 * user's contacts and records the call to history.
 */
class DialerController extends Controller
{
    use ApiResponses;

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'number_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ]);
        $userId = $request->user()->id;

        $contact = null;
        $matchedPhone = null;
        if (Schema::hasTable('contact_phones')) {
            $cp = ContactPhone::where('value_e164', $data['number_e164'])
                ->whereHas('contact', fn ($q) => $q->where('user_id', $userId))
                ->with('contact')
                ->first();
            $contact = $cp?->contact;
            $matchedPhone = $cp?->value_e164;
        }

        DialerLookup::create([
            'user_id'      => $userId,
            'number_e164'  => $data['number_e164'],
            'contact_id'   => $contact?->id,
            'looked_up_at' => now(),
        ]);

        return $this->ok([
            'number_e164' => $data['number_e164'],
            'contact'     => $contact ? [
                'id'           => $contact->id,
                'display_name' => $contact->display_name,
                'organization' => $contact->organization,
                'phone'        => $matchedPhone,
            ] : null,
        ]);
    }

    public function history(Request $request)
    {
        $items = DialerLookup::where('user_id', $request->user()->id)
            ->orderByDesc('looked_up_at')
            ->limit(100)
            ->get(['id', 'number_e164', 'contact_id', 'looked_up_at']);
        return $this->ok(['items' => $items->map(fn ($r) => [
            'id'           => $r->id,
            'number_e164'  => $r->number_e164,
            'contact_id'   => $r->contact_id,
            'looked_up_at' => optional($r->looked_up_at)->toIso8601String(),
        ])->all()]);
    }
}
