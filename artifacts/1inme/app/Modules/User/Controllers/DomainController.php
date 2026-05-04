<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
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
        $globalDomains = Domain::whereNull('user_id')
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('plans');
                if ($user->plan_id) {
                    $q->orWhereHas('plans', fn ($p) => $p->where('plans.id', $user->plan_id));
                }
            })
            ->orderBy('domain')
            ->get();

        return view('user.domains.index', compact('myDomains', 'globalDomains'));
    }

    public function store(Request $request)
    {
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

        $expected = $domain->cname_target ?: parse_url(config('app.url'), PHP_URL_HOST);
        $records  = @dns_get_record($domain->domain, DNS_CNAME);
        $matched  = false;
        if (is_array($records)) {
            foreach ($records as $r) {
                if (!empty($r['target']) && rtrim(strtolower($r['target']), '.') === strtolower($expected)) {
                    $matched = true;
                    break;
                }
            }
        }

        if (!$matched) {
            return back()->with('error', "CNAME for {$domain->domain} does not point at {$expected} yet. DNS changes can take up to 24 hours to propagate.");
        }

        $domain->update([
            'is_verified'                    => true,
            'verified_at'                    => now(),
            'dns_status'                     => Domain::DNS_STATUS_HEALTHY,
            'dns_last_checked_at'            => now(),
            'dns_last_target'                => strtolower($expected),
            'dns_drift_started_at'           => null,
            'dns_drift_notified_at'          => null,
            'dns_unverified_warning_sent_at' => null,
        ]);
        WorkspaceActivityRecorder::record(null, 'domain.verify', 'domain', $domain->id, $domain->domain, route('user.domains.index'));
        $this->recordAudit(SensitiveActionLogger::ACTION_DOMAIN_VERIFIED, $domain);
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
