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
        $user = $request->user();

        // Per-plan numeric cap on how many of their own domains a user may
        // connect. Mirrors the web domain-connect path: the `custom_domains`
        // boolean governs whether the feature is available at all; this caps
        // the count. -1 = unlimited, 0 = none. Fall back to unlimited when the
        // key is unset so plans predating this limit keep working until an
        // admin sets a value (the seeder backfills it).
        // getPlanFeature honors the `user.plan_limits.bypass` permission
        // (bypass holders get an effectively-unlimited cap) and addons.
        $maxDomains = (int) $user->getPlanFeature('max_custom_domains', -1);
        if ($maxDomains !== -1) {
            $current = $user->domains()->count();
            if ($current >= $maxDomains) {
                $message = $maxDomains === 0
                    ? "Your current plan doesn't allow connecting your own domains. Upgrade your plan to connect a custom domain."
                    : "You've reached your plan's custom-domain limit ({$maxDomains}). Upgrade your plan to connect more domains.";
                return $this->fail($message, 403, 'plan_limit');
            }
        }

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
            return $this->fail('This domain is already claimed on Sayzio.', 409, 'domain_taken');
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

    /**
     * DNS-propagation probe + verify. Mirrors the web verify action via the
     * shared DomainDnsVerifier: an unpropagated CNAME is an expected state
     * (200 with verified=false), so mobile can poll this while the user's
     * DNS change propagates and flip the badge live on success.
     */
    public function verify(Request $request, int $id)
    {
        $d = Domain::where('user_id', $request->user()->id)->find($id);
        if (!$d) return $this->notFound('Domain not found');

        if ($d->is_verified) {
            return $this->ok(['verified' => true, 'domain' => $this->transform($d)]);
        }

        $expected = \App\Modules\Common\Services\DomainDnsVerifier::expectedTarget($d);
        if (!\App\Modules\Common\Services\DomainDnsVerifier::cnameMatches($d, $expected)) {
            return $this->ok([
                'verified'       => false,
                'expected_cname' => $expected,
                'domain'         => $this->transform($d),
            ]);
        }

        \App\Modules\Common\Services\DomainDnsVerifier::markVerified($d, $expected);
        return $this->ok(['verified' => true, 'domain' => $this->transform($d->fresh())]);
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
