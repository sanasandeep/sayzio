<?php

namespace App\Console\Commands;

use App\Services\AI\AiMindProvisioner;
use Illuminate\Console\Command;

/**
 * Periodic, sign-up-independent refresh of the platform-managed
 * "Sayzio Default Mind".
 *
 * Historically {@see AiMindProvisioner::ensurePlatformDefault()} ran on EVERY
 * account creation, so its side effects — attaching any newly-declared public
 * feature snapshots ({@see AiMindProvisioner::PLATFORM_FEATURE_KEYS}) and
 * recounting stats — happened to re-run whenever anyone signed up. Task #4596
 * made the `User::created` hook dispatch provisioning ONLY when the platform
 * default Mind is entirely missing, so that incidental periodic refresh no
 * longer piggy-backs on sign-ups.
 *
 * The pricing/feature *content* itself is answered live at query time (see
 * AiMindFeatureAdapter::publicSnapshot), so catalogue/price changes are never
 * stale. What this command guarantees is the remaining, non-live part: that a
 * newly-added feature-snapshot key is attached to the platform Mind and its
 * stats stay current, without depending on an admin clicking "Re-seed default"
 * or a user happening to open an AI dashboard (the lazy ensureForUser path).
 *
 * The provisioner is fully idempotent, so running this on a schedule can never
 * create duplicates or disturb an already-current Mind.
 */
class ReseedPlatformAiMind extends Command
{
    protected $signature = 'minds:reseed-platform';
    protected $description = 'Refresh the platform default AI Mind: attach any new public feature sources and recount stats (idempotent).';

    public function handle(): int
    {
        $mind = AiMindProvisioner::ensurePlatformDefault();
        $this->info("Platform default Mind refreshed (#{$mind->id}, {$mind->sources_count} sources).");

        return self::SUCCESS;
    }
}
