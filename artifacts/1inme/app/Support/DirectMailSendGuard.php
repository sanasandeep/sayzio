<?php

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Single source of truth for the outbound-mail bypass guard.
 *
 * The central {@see \App\Modules\Common\Services\Emailer} pipeline (and the
 * {@see \App\Listeners\LogOutboundEmail} catch-all it feeds) is where every
 * outbound message is supposed to flow: it is what NonOtpEmailIsolationTest and
 * OtpLoginEmailIsolationTest prove gets black-holed to the non-delivering "log"
 * transport in dev/CI and never hard-forces a real SMTP socket. Those
 * guarantees only cover code that actually goes through the pipeline.
 *
 * A developer who instead reaches for the raw facade at a brand-new call site —
 * `Mail::to`, `Mail::raw`, `Mail::html`, `Mail::mailer`, or a `Notification`
 * routed to the "mail" channel — sidesteps that coverage entirely and could
 * open a real relay in non-production. Blasting fixture mail through the
 * live relay is exactly the abuse-block failure mode that once knocked out
 * production OTP delivery, so a fresh bypass must be caught in review, not in
 * an incident.
 *
 * This class holds the {@see self::SEND_PATTERNS} + {@see self::ALLOWLIST} and
 * the source-scanning logic, so the in-suite drift test
 * ({@see \Tests\Feature\DirectMailSendAllowlistTest}) and the standalone
 * `mail:check-direct-sends` artisan command
 * ({@see \App\Console\Commands\CheckDirectMailSends}) share ONE definition and
 * can never drift apart. No database is required — it walks the PHP files under
 * app/ only.
 */
class DirectMailSendGuard
{
    /**
     * Regexes that identify a direct outbound-mail send through the facade,
     * i.e. one that bypasses the Emailer pipeline. Each requires an actual
     * call `(` so prose/doc-comment mentions of `Mail::send` don't false-fire.
     */
    public const SEND_PATTERNS = [
        '/\bMail::(?:to|raw|html|send|queue|later|mailer)\s*\(/',
        '/Notification::route\s*\(\s*[\'"]mail[\'"]/',
    ];

    /**
     * Files (relative to app/) that are permitted to send mail directly.
     *
     * ONLY add a path here after confirming the new sender genuinely cannot go
     * through the Emailer pipeline (e.g. an SMTP connection-verify probe or a
     * deliberate bulk broadcast) and that it does NOT hard-force a delivering
     * transport in non-production. Keep the categories below intact so the next
     * reviewer can see why each exception exists.
     */
    public const ALLOWLIST = [
        // --- The pipeline itself (the safe path) -------------------------
        'Modules/Common/Services/Emailer.php',        // the central send pipeline
        // NOTE: App\Listeners\LogOutboundEmail only LISTENS to MessageSent and
        // writes an activity-log row — it never sends mail — so it is
        // deliberately absent here (its doc comment mentions Mail::send in
        // prose, which the paren-requiring SEND_PATTERNS correctly ignore).

        // --- SMTP connection-verify / "send a test email" surfaces -------
        'Services/Integrations/MailSettings.php',            // admin SMTP verify probe
        'Services/Billing/CompanyMailSettings.php',          // per-company SMTP verify + send
        'Modules/Admin/Controllers/MailSettingsController.php', // admin "test email" button
        'Modules/Api/Controllers/MailSettingsController.php',   // mobile-admin "test email"
    ];

    /**
     * Walk app/ and return the relative paths of files that send mail directly
     * yet are NOT on {@see self::ALLOWLIST}. Empty array === clean.
     *
     * @return list<string> sorted relative paths
     */
    public static function offenders(): array
    {
        $appPath = app_path();
        $allowlist = array_flip(self::ALLOWLIST);

        $offenders = [];

        foreach (self::phpFiles($appPath) as $file) {
            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', ltrim(substr($absolute, strlen($appPath)), '/\\'));

            if (! self::sendsMailDirectly((string) file_get_contents($absolute))) {
                continue;
            }

            if (! isset($allowlist[$relative])) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Return allowlist entries that no longer point at a file which sends mail
     * directly (file deleted, or the direct send was removed), so the list
     * keeps documenting the real bypass surface. Empty array === clean.
     *
     * @return list<string> sorted "path (reason)" strings
     */
    public static function staleEntries(): array
    {
        $appPath = app_path();

        $stale = [];

        foreach (self::ALLOWLIST as $relative) {
            $absolute = $appPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($absolute)) {
                $stale[] = "{$relative} (file no longer exists)";
                continue;
            }

            if (! self::sendsMailDirectly((string) file_get_contents($absolute))) {
                $stale[] = "{$relative} (no longer sends mail directly)";
            }
        }

        sort($stale);

        return $stale;
    }

    /**
     * Does the given PHP source send mail directly via the facade / route?
     */
    public static function sendsMailDirectly(string $contents): bool
    {
        foreach (self::SEND_PATTERNS as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private static function phpFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
