<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = Domain::where('user_id', $request->user()->id)->orderBy('domain')->get();
        return $this->ok(['items' => $items->map(fn ($d) => $this->transform($d))->all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'type'   => ['nullable', Rule::in(['custom', 'subdomain'])],
        ]);

        // Claim lock: refuse if any other account already owns a row for
        // this hostname — even if it's currently drifting/unverified.
        // The original creator keeps the row through the drift+grace
        // window so they can recover after fixing DNS, and we don't
        // want a competing account to swoop in mid-window.
        $existing = Domain::where('domain', strtolower($data['domain']))->first();
        if ($existing) {
            return $this->fail('This domain is already claimed on 1INME.', 409, 'domain_taken');
        }

        $d = Domain::create([
            'user_id'            => $request->user()->id,
            'domain'             => strtolower($data['domain']),
            'type'               => $data['type'] ?? 'custom',
            'is_verified'        => false,
            'is_active'          => false,
            'verification_token' => Str::random(40),
        ]);
        return $this->created(['domain' => $this->transform($d)]);
    }

    public function destroy(Request $request, int $id)
    {
        $d = Domain::where('user_id', $request->user()->id)->find($id);
        if (!$d) return $this->notFound('Domain not found');
        $d->delete();
        return $this->noContent();
    }

    protected function transform(Domain $d): array
    {
        return [
            'id'                 => $d->id,
            'domain'             => $d->domain,
            'type'               => $d->type,
            'is_verified'        => (bool) $d->is_verified,
            'is_active'          => (bool) $d->is_active,
            'verification_token' => $d->verification_token,
            'cname_target'       => $d->cname_target,
            'verified_at'        => optional($d->verified_at)->toIso8601String(),
        ];
    }
}
