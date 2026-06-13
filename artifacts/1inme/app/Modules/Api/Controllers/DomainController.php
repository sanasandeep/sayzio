<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\PlatformHosts;
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

    /**
     * Domains the caller can attach a link to — their own verified+active
     * domains plus admin-global domains tagged for their plan — together
     * with the admin-chosen primary global domain and the platform's env
     * default host. Mirrors the web create/edit link form so the mobile
     * flows can pre-select the same default. Also reports whether the
     * caller may set the platform primary (admin only).
     */
    public function available(Request $request)
    {
        $user  = $request->user();
        $items = Domain::availableTo($user)->get();

        $primary = $items->firstWhere(fn ($d) => $d->isGlobal() && $d->is_primary);

        return $this->ok([
            'items'             => $items->map(fn ($d) => $this->transform($d))->all(),
            'primary_domain_id' => $primary?->id,
            'default_host'      => PlatformHosts::primary(),
            'can_manage'        => (bool) $user->hasPermission('settings.manage'),
        ]);
    }

    /**
     * Mark a global domain as the platform-wide primary. Admin-only:
     * gated behind the same `settings.manage` permission as the web
     * admin "Make primary" control. Only global (admin-owned) domains
     * can ever be primary.
     */
    public function makePrimary(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user->hasPermission('settings.manage')) {
            return $this->fail('You are not allowed to change the platform domain.', 403, 'forbidden');
        }

        $domain = Domain::whereNull('user_id')->find($id);
        if (!$domain) return $this->notFound('Global domain not found');

        $domain->makePrimary();
        return $this->ok(['domain' => $this->transform($domain->fresh())]);
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

        $d = new Domain([
            'user_id'            => $request->user()->id,
            'domain'             => strtolower($data['domain']),
            'type'               => $data['type'] ?? 'custom',
            'is_verified'        => false,
            'is_active'          => false,
            'verification_token' => Str::random(40),
        ]);
        $d->workspace_id = $this->activeWorkspaceId($request->user());
        $d->save();
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
            'is_primary'         => (bool) $d->is_primary,
            'is_global'          => $d->isGlobal(),
            'verification_token' => $d->verification_token,
            'cname_target'       => $d->cname_target,
            'verified_at'        => optional($d->verified_at)->toIso8601String(),
        ];
    }
}
