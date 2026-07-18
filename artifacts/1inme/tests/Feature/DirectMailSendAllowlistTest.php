<?php

namespace Tests\Feature;

use App\Support\DirectMailSendGuard;
use Tests\TestCase;

/**
 * In-suite mirror of the `mail:check-direct-sends` drift guard
 * ({@see \App\Console\Commands\CheckDirectMailSends}).
 *
 * Guards the outbound-mail safety boundary against NEW bypass call sites.
 *
 * The central {@see \App\Modules\Common\Services\Emailer} pipeline (and the
 * {@see \App\Listeners\LogOutboundEmail} catch-all it feeds) is where every
 * outbound message is supposed to flow: it is what NonOtpEmailIsolationTest and
 * OtpLoginEmailIsolationTest prove gets black-holed to the non-delivering "log"
 * transport in dev/CI and never hard-forces a real SMTP socket. Those
 * guarantees only cover code that actually goes through the pipeline.
 *
 * A developer who instead reaches for the raw facade at a brand-new call site —
 * `Mail::to()->send()`, `Mail::raw()`, `Mail::html()`, `Mail::mailer('smtp')`,
 * or `Notification::route('mail', ...)` — sidesteps that coverage entirely and
 * could open a real relay in non-production. Blasting fixture mail through the
 * live relay is exactly the abuse-block failure mode that once knocked out
 * production OTP delivery, so a fresh bypass must be caught in review, not in
 * an incident.
 *
 * The SEND_PATTERNS + ALLOWLIST and the source-scan live in
 * {@see DirectMailSendGuard} so this in-suite test and the standalone
 * `mail:check-direct-sends` command share ONE definition and can't drift. This
 * test asserts drift is caught in the regular DB-backed run too, not only by
 * the fast standalone validation command. It needs no database — it walks the
 * PHP files under every root in DirectMailSendGuard::SCAN_ROOTS (app/, routes/,
 * database/), so a raw send in a closure route, a scheduled job, or a seeder is
 * caught just like one in app/.
 */
class DirectMailSendAllowlistTest extends TestCase
{
    public function test_no_unallowlisted_file_sends_mail_directly(): void
    {
        $offenders = DirectMailSendGuard::offenders();

        $this->assertSame(
            [],
            $offenders,
            "The following scanned file(s) send mail directly via the Mail facade / "
            . "Notification::route('mail') and bypass the Emailer safety pipeline:\n\n  "
            . implode("\n  ", $offenders)
            . "\n\nRoute new mail through App\\Modules\\Common\\Services\\Emailer. If a "
            . "direct send is genuinely unavoidable (SMTP verify, deliberate bulk "
            . "broadcast), add the path to App\\Support\\DirectMailSendGuard::ALLOWLIST in "
            . "a reviewed change."
        );
    }

    public function test_allowlist_has_no_stale_entries(): void
    {
        $stale = DirectMailSendGuard::staleEntries();

        $this->assertSame(
            [],
            $stale,
            "App\\Support\\DirectMailSendGuard::ALLOWLIST contains stale entries. A path "
            . "that no longer sends mail directly should be removed so the allowlist "
            . "keeps documenting the real bypass surface:\n\n  "
            . implode("\n  ", $stale)
        );
    }

    public function test_check_direct_sends_command_passes(): void
    {
        $this->artisan('mail:check-direct-sends')->assertExitCode(0);
    }
}
