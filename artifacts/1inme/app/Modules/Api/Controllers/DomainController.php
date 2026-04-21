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
