<?php

namespace App\Console\Commands;

use App\Support\DirectMailSendGuard;
use Illuminate\Console\Command;

/**
 * Standalone CI drift guard for the outbound-mail bypass boundary.
 *
 * In-suite mirror: {@see \Tests\Feature\DirectMailSendAllowlistTest}. Both this
 * command and that test delegate to {@see DirectMailSendGuard} so the
 * SEND_PATTERNS + ALLOWLIST can never drift apart.
 *
 * Why a standalone command in addition to the test: the in-suite guard only
 * fires inside the full DB-backed PHPUnit run, so a fresh bypass call site can
 * sit unflagged until that whole suite runs. This command re-uses the exact
 * same source-scan with NO database, so its own `mail-direct-sends` validation
 * workflow catches drift fast in CI — mirroring how the sibling demo-write
 * guard pairs `demo:check-allowlist` with DemoAllowlistDriftTest.
 *
 * Exit codes:
 *   0 — every scanned mail send goes through Emailer or is a documented
 *       exception, and no allowlist entry is stale.
 *   1 — drift: an unallowlisted direct sender and/or a stale allowlist entry.
 */
class CheckDirectMailSends extends Command
{
    protected $signature = 'mail:check-direct-sends';

    protected $description = 'Fail when a scanned file (app/, routes/, database/) sends mail directly via the Mail facade / Notification::route(\'mail\') outside the documented allowlist.';

    public function handle(): int
    {
        $offenders = DirectMailSendGuard::offenders();
        $stale = DirectMailSendGuard::staleEntries();

        if (empty($offenders) && empty($stale)) {
            $this->info('OK — every scanned mail send flows through the Emailer pipeline or is a documented exception, and no allowlist entry is stale.');

            return self::SUCCESS;
        }

        if (! empty($offenders)) {
            $this->error('Outbound-mail bypass — ' . count($offenders) . ' file(s) send mail directly via the Mail facade / Notification::route(\'mail\'):');
            $this->newLine();
            foreach ($offenders as $offender) {
                $this->line("  <fg=yellow>{$offender}</>");
            }
            $this->newLine();
            $this->line('Route new mail through App\\Modules\\Common\\Services\\Emailer. If a direct send is');
            $this->line('genuinely unavoidable (SMTP verify, deliberate bulk broadcast), add the path to');
            $this->line('App\\Support\\DirectMailSendGuard::ALLOWLIST in a reviewed change.');
            $this->newLine();
        }

        if (! empty($stale)) {
            $this->error('DirectMailSendGuard::ALLOWLIST has stale entries:');
            foreach ($stale as $entry) {
                $this->line("  <fg=yellow>{$entry}</>");
            }
            $this->line('Remove them so the allowlist keeps documenting the real bypass surface.');
            $this->newLine();
        }

        return self::FAILURE;
    }
}
