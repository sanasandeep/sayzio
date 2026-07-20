<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Domain;
use App\Modules\User\Services\WorkspaceActivityRecorder;

/**
 * Shared DNS-propagation check for custom domains. Used by both the web
 * verify action (form POST + JS polling probe) and the mobile API verify
 * endpoint so the CNAME-matching rules can never drift between surfaces.
 */
class DomainDnsVerifier
{
    /** The CNAME target the domain is expected to point at. */
    public static function expectedTarget(Domain $domain): string
    {
        return $domain->cname_target ?: parse_url(config('app.url'), PHP_URL_HOST);
    }

    /** True when the domain's live CNAME record points at the expected target. */
    public static function cnameMatches(Domain $domain, string $expected): bool
    {
        $records = @dns_get_record($domain->domain, DNS_CNAME);
        if (!is_array($records)) return false;
        foreach ($records as $r) {
            if (!empty($r['target']) && rtrim(strtolower($r['target']), '.') === strtolower($expected)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Flip the domain to verified/healthy, queue HTTPS issuance and record
     * the workspace activity entry. Callers layer any surface-specific
     * auditing (e.g. the web sensitive-action ledger) on top.
     */
    public static function markVerified(Domain $domain, string $expected): void
    {
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
        // Queue automatic HTTPS issuance (EC2 deployments): reset the SSL
        // state so the scheduled domains:issue-certificates run picks this
        // domain up on its next tick. No-op cost when auto-issue is off.
        SslCertificateIssuer::markPending($domain->fresh());
        WorkspaceActivityRecorder::record(null, 'domain.verify', 'domain', $domain->id, $domain->domain, route('user.domains.index'));
    }
}
