<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
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
 * This is a source-scanning drift guard (no database): it walks every PHP file
 * under app/ and fails if one sends mail directly via the facade unless the
 * file is on {@see self::ALLOWLIST}. Adding a legitimate new sender is a
 * deliberate, reviewed one-line addition to that list.
 */
class DirectMailSendAllowlistTest extends TestCase
{
    /**
     * Regexes that identify a direct outbound-mail send through the facade,
     * i.e. one that bypasses the Emailer pipeline. Each requires an actual
     * call `(` so prose/doc-comment mentions of `Mail::send` don't false-fire.
     */
    private const SEND_PATTERNS = [
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
    private const ALLOWLIST = [
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

        // --- Deliberate bulk / direct broadcast senders ------------------
        'Modules/Admin/Controllers/NewsletterController.php',   // admin newsletter blast
        'Modules/Admin/Controllers/Blog/CommentController.php', // blog comment notifications
        'Modules/Admin/Controllers/UserManagementController.php', // admin -> user message
        'Modules/User/Controllers/SubscriberController.php',    // creator -> subscribers compose/send
        'Modules/User/Controllers/CreatorPostController.php',   // creator post -> followers
        'Modules/User/Controllers/FormController.php',          // form-submission notifications
        'Modules/User/Controllers/InboxController.php',         // inbox reply send
        'Modules/User/Controllers/ClientPortalController.php',  // client-portal invite/notice
        'Modules/Api/Controllers/ClientPortalController.php',   // client-portal (mobile API)
        'Modules/Common/Controllers/SitePageController.php',    // contact-form relay
        'Modules/User/Services/InboxForwarder.php',             // inbox forwarding
        'Modules/User/Services/Inbox/InboxReplyDispatcher.php', // inbox reply dispatcher
    ];

    public function test_no_unallowlisted_file_sends_mail_directly(): void
    {
        $appPath = app_path();
        $allowlist = array_flip(self::ALLOWLIST);

        $offenders = [];

        foreach ($this->phpFiles($appPath) as $file) {
            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', ltrim(substr($absolute, strlen($appPath)), '/\\'));

            $contents = (string) file_get_contents($absolute);

            if (! $this->sendsMailDirectly($contents)) {
                continue;
            }

            if (! isset($allowlist[$relative])) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "The following app/ file(s) send mail directly via the Mail facade / "
            . "Notification::route('mail') and bypass the Emailer safety pipeline:\n\n  "
            . implode("\n  ", $offenders)
            . "\n\nRoute new mail through App\\Modules\\Common\\Services\\Emailer. If a "
            . "direct send is genuinely unavoidable (SMTP verify, deliberate bulk "
            . "broadcast), add the path to DirectMailSendAllowlistTest::ALLOWLIST in a "
            . "reviewed change."
        );
    }

    public function test_allowlist_has_no_stale_entries(): void
    {
        $appPath = app_path();

        $stale = [];

        foreach (self::ALLOWLIST as $relative) {
            $absolute = $appPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($absolute)) {
                $stale[] = "{$relative} (file no longer exists)";
                continue;
            }

            if (! $this->sendsMailDirectly((string) file_get_contents($absolute))) {
                $stale[] = "{$relative} (no longer sends mail directly)";
            }
        }

        sort($stale);

        $this->assertSame(
            [],
            $stale,
            "DirectMailSendAllowlistTest::ALLOWLIST contains stale entries. A path "
            . "that no longer sends mail directly should be removed so the allowlist "
            . "keeps documenting the real bypass surface:\n\n  "
            . implode("\n  ", $stale)
        );
    }

    private function sendsMailDirectly(string $contents): bool
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
    private function phpFiles(string $root): iterable
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
