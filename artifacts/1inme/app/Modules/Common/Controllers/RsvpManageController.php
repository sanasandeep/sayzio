<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Http\Request;

/**
 * Token-authenticated public manage page for an existing RSVP. Lets a
 * guest update their response, change plus-ones, edit custom answers,
 * or cancel — without forcing them to sign up. The token is minted at
 * RSVP creation time and emailed in the confirmation message.
 */
class RsvpManageController extends Controller
{
    public function show(Request $request, string $alias, string $token)
    {
        [$link, $rsvp] = $this->resolve($alias, $token);
        return view('common.rsvp-manage', compact('link', 'rsvp'));
    }

    public function update(Request $request, string $alias, string $token)
    {
        [$link, $rsvp] = $this->resolve($alias, $token);
        $s = (array) ($link->settings ?? []);
        $rsvpSettings = (array) ($s['rsvp_settings'] ?? []);
        $allowPlusOnes = !empty($s['rsvp_allow_plus_ones']);

        $rules = [
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:160'],
            'response'  => ['required', 'in:yes,no,maybe'],
            'plus_ones' => ['nullable', 'integer', 'min:0', 'max:20'],
            'message'   => ['nullable', 'string', 'max:1000'],
            'company'   => ['nullable', 'string', 'max:191'],
            'role'      => ['nullable', 'string', 'max:191'],
            'phone'     => ['nullable', 'string', 'max:40'],
            'occurrences'   => ['nullable', 'array', 'max:50'],
            'occurrences.*' => ['string', 'max:64'],
            'answers'       => ['nullable', 'array', 'max:50'],
        ];
        $data = $request->validate($rules);

        $rsvp->fill([
            'name'      => $data['name'],
            'email'     => $data['email'] ?? $rsvp->email,
            'phone'     => $data['phone'] ?? $rsvp->phone,
            'company'   => $data['company'] ?? null,
            'role'      => $data['role'] ?? null,
            'response'  => $data['response'],
            'plus_ones' => $allowPlusOnes ? (int) ($data['plus_ones'] ?? 0) : 0,
            'message'   => $data['message'] ?? null,
            'occurrences' => $data['occurrences'] ?? null,
            'answers'     => $this->sanitizeAnswers($data['answers'] ?? null, $rsvpSettings['questions'] ?? []),
        ]);

        // Bumped from waitlist? Recompute capacity.
        if ($rsvp->status === 'cancelled' && $data['response'] !== 'no') {
            $rsvp->status = 'confirmed';
        }
        $rsvp->save();

        // Task #3606: keep the RSVP's QR check-in ticket in sync with the
        // (possibly just-changed) response/status.
        \App\Services\Events\RsvpTicketService::sync($rsvp);

        return redirect()->route('redirect.rsvp.manage', [$alias, $token])
            ->with('success', 'Your RSVP was updated.');
    }

    public function cancel(Request $request, string $alias, string $token)
    {
        [, $rsvp] = $this->resolve($alias, $token);
        $rsvp->update(['status' => 'cancelled', 'response' => 'no']);
        \App\Services\Events\RsvpTicketService::sync($rsvp);
        return redirect()->route('redirect.rsvp.manage', [$alias, $token])
            ->with('success', 'Your RSVP has been cancelled.');
    }

    private function resolve(string $alias, string $token): array
    {
        $link = Link::resolveByAlias($alias, request()->getHost());
        abort_unless($link && $link->type === 'ics', 404);
        $rsvp = Rsvp::where('link_id', $link->id)->where('manage_token', $token)->first();
        abort_unless($rsvp, 404);
        $link->loadMissing('icsData');
        return [$link, $rsvp];
    }

    private function sanitizeAnswers($input, array $questions): ?array
    {
        if (!is_array($input) || empty($questions)) return null;
        $out = [];
        foreach ($questions as $q) {
            $label = trim((string) ($q['label'] ?? ''));
            if ($label === '') continue;
            if (!array_key_exists($label, $input)) continue;
            $val = $input[$label];
            if (is_array($val)) {
                $val = array_values(array_filter(array_map('strval', $val), fn ($x) => $x !== ''));
                if (!empty($val)) $out[$label] = array_slice($val, 0, 20);
            } else {
                $val = trim((string) $val);
                if ($val !== '') $out[$label] = mb_substr($val, 0, 1000);
            }
        }
        return $out ?: null;
    }
}
