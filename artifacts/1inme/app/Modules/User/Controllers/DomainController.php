<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\DomainDnsVerifier;
use App\Modules\User\Models\Domain;
use App\Modules\User\Services\SensitiveActionLogger;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $myDomains     = $user->domains()->orderBy('domain')->get();
        // Team-aware + badge-aware: the global domains the active workspace
        // owner's plan and account badges unlock (untagged ones open to all).
        $globalDomains = Domain::globalAvailableTo($user)->get();

        return view('user.domains.index', compact('myDomains', 'globalDomains'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Per-plan numeric cap on how many of their own domains a user may
        // connect. The `custom_domains` boolean (enforced by the route's
        // CheckPlanLimit middleware) already governs whether the feature is
        // available at all; this caps the count. -1 = unlimited. We fall back
        // to unlimited when the key is unset so plans predating this limit keep
        // working until an admin sets a value (the seeder backfills it).
        $maxDomains = (int) ($user->plan?->features['max_custom_domains'] ?? -1);
        if ($maxDomains !== -1) {
            $current = $user->domains()->count();
            if ($current >= $maxDomains) {
                $message = $maxDomains === 0
                    ? "Your current plan doesn't allow connecting your own domains. Upgrade your plan to connect a custom domain."
                    : "You've reached your plan's custom-domain limit ({$maxDomains}). Upgrade your plan to connect more domains.";
                return back()->with('error', $message);
            }
        }

        $data = $request->validate([
            'domain' => 'required|string|max:191|regex:/^[a-z0-9.\-]+\.[a-z]{2,}$/i|unique:domains,domain',
        ]);

        $domain = Domain::create([
            'user_id'            => $request->user()->id,
            'domain'             => strtolower($data['domain']),
            'is_active'          => true,
            'is_verified'        => false,
            'verification_token' => Str::random(32),
            'cname_target'       => parse_url(config('app.url'), PHP_URL_HOST),
            'type'               => 'redirect',
        ]);

        WorkspaceActivityRecorder::record(null, 'domain.add', 'domain', $domain->id, $domain->domain, route('user.domains.index'));
        $this->recordAudit(SensitiveActionLogger::ACTION_DOMAIN_ADDED, $domain);

        return redirect()->route('user.domains.index')
            ->with('success', "Domain {$domain->domain} added. Point a CNAME record to {$domain->cname_target}, then click Verify.");
    }

    public function verify(Request $request, Domain $domain)
    {
        abort_if($domain->user_id !== $request->user()->id, 403);

        // Already verified (e.g. a stale polling probe raced a manual
        // verify): report success without re-running the DNS lookup.
        if ($domain->is_verified) {
            if ($request->expectsJson()) {
                return response()->json(['verified' => true]);
            }
            return back()->with('success', "Domain {$domain->domain} is already verified.");
        }

        $expected = DomainDnsVerifier::expectedTarget($domain);
        if (!DomainDnsVerifier::cnameMatches($domain, $expected)) {
            // JSON probe (background propagation polling): a not-yet-propagated
            // CNAME is an expected state, not an error — respond 200.
            if ($request->expectsJson()) {
                return response()->json(['verified' => false]);
            }
            return back()->with('error', "CNAME for {$domain->domain} does not point at {$expected} yet. DNS changes can take up to 24 hours to propagate.");
        }

        DomainDnsVerifier::markVerified($domain, $expected);
        $this->recordAudit(SensitiveActionLogger::ACTION_DOMAIN_VERIFIED, $domain);
        if ($request->expectsJson()) {
            return response()->json(['verified' => true]);
        }
        return back()->with('success', "Domain {$domain->domain} verified — short links can now use it.");
    }

    public function destroy(Request $request, Domain $domain)
    {
        abort_if($domain->user_id !== $request->user()->id, 403);
        $label = $domain->domain;
        $domainId = $domain->id;
        $snapshot = $domain->replicate();
        $snapshot->id = $domain->id;
        $domain->delete();
        WorkspaceActivityRecorder::record(null, 'domain.remove', 'domain', $domainId, $label, route('user.domains.index'));
        $this->recordAudit(SensitiveActionLogger::ACTION_DOMAIN_REMOVED, $snapshot);
        return back()->with('success', 'Domain removed.');
    }

    /**
     * Append a workspace-audit row for a custom-domain change. Custom
     * domains are workspace-sensitive (they control where short links
     * resolve) so they fall under the sensitive-action ledger.
     */
    protected function recordAudit(string $action, Domain $domain): void
    {
        if (!app()->bound('current_workspace')) return;
        app(SensitiveActionLogger::class)->record(
            app('current_workspace'),
            $action,
            'domain',
            $domain->id,
            $domain->domain,
            ['type' => $domain->type, 'verified' => (bool) $domain->is_verified],
        );
    }
}
