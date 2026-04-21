<?php

namespace App\Console\Commands;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use App\Modules\User\Services\CloudFiles\CloudConnectionBrokenMail;
use App\Modules\User\Services\CloudFiles\CloudProviderRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sweeps every cloud_connection across every workspace, refreshes near-expiry
 * tokens via stored refresh_tokens, and pings the provider with a tiny
 * listing call to verify the access_token is still honoured. Connections
 * that fail are flagged with last_error / last_error_at so the in-app banner
 * (rendered in the user layout) and the connections page can surface a
 * "Reconnect needed" prompt; the connection's owner also gets a one-shot
 * email — deduped by COOLDOWN_DAYS in the mailer.
 *
 * Connections that recover (refresh + ping both succeed) get last_error
 * cleared so the banner / Reconnect badge disappears on the next page load.
 *
 * Workspace scoping:
 *   The command runs from CLI where no `current_workspace` is bound, so
 *   the BelongsToWorkspace global scope is automatically skipped — the
 *   query naturally spans every workspace's connections.
 */
class CheckCloudConnections extends Command
{
    protected $signature   = 'cloud-connections:check
                              {--connection= : Check only this connection id}
                              {--no-mail : Skip the broken-connection email even on a fresh failure}';

    protected $description = 'Refresh + ping every cloud_connection and surface broken ones (banner + email).';

    public function handle(CloudProviderRegistry $registry): int
    {
        $q = CloudConnection::query();
        if ($id = $this->option('connection')) {
            $q->where('id', $id);
        }
        $connections = $q->get();
        $this->info("Checking {$connections->count()} cloud connection(s)...");

        // Cache the per-(workspace,provider) OAuth app row; a single workspace
        // typically has at most 3 apps, but a sweep across many workspaces
        // would otherwise re-fetch the same app once per connection.
        $apps = [];
        $appFor = function (CloudConnection $c) use (&$apps): ?CloudProviderApp {
            $key = $c->workspace_id . ':' . $c->provider;
            if (array_key_exists($key, $apps)) return $apps[$key];
            $apps[$key] = CloudProviderApp::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $c->workspace_id)
                ->where('provider', $c->provider)
                ->first();
            return $apps[$key];
        };

        $ok = $broken = $skipped = $emails = 0;
        foreach ($connections as $c) {
            // Snapshot BEFORE any mutation in this iteration: we use this to
            // decide whether the connection is freshly-broken (email worthy)
            // or merely staying broken (already alerted on).
            $wasBroken = filled($c->last_error);

            $app = $appFor($c);
            if (!$app || !$app->isConfigured()) {
                // App credentials were removed / disabled — not the user's
                // fault and re-connecting won't help. Leave untouched.
                $skipped++;
                continue;
            }

            try {
                $c = $registry->refreshIfExpiring($c, $app);

                // Tiny ping: list the root folder. We don't care about the
                // payload — only that the access_token is still accepted.
                // We deliberately ping even if refreshIfExpiring quietly
                // recorded a refresh failure: the existing access_token may
                // still be valid (not expired yet), in which case the
                // connection is functional and the previous error should
                // clear automatically.
                $registry->get($c->provider)->listFolder($c, null);

                $c->forceFill([
                    'last_error'      => null,
                    'last_error_at'   => null,
                    'last_synced_at'  => now(),
                    'last_checked_at' => now(),
                ])->save();
                $ok++;
                $this->line("  #{$c->id} {$c->provider}: " . ($wasBroken ? 'recovered' : 'ok'));
            } catch (Throwable $e) {
                $msg = substr($e->getMessage(), 0, 240);
                $c->forceFill([
                    'last_error'      => $msg,
                    'last_error_at'   => now(),
                    'last_checked_at' => now(),
                ])->save();
                $broken++;
                $this->warn("  #{$c->id} {$c->provider}: {$msg}");

                // Email only on the freshly-broken transition. Repeated
                // failures of an already-broken connection are throttled by
                // the 7-day cooldown inside the mailer, but skipping here
                // avoids touching the cooldown column needlessly.
                if (!$wasBroken && !$this->option('no-mail')) {
                    if (CloudConnectionBrokenMail::dispatchIfDue($c, $msg)) {
                        $emails++;
                        $this->line("    -> emailed reconnect prompt to user #{$c->user_id}");
                    }
                }
            }
        }

        $this->info("Done. ok={$ok} broken={$broken} skipped={$skipped} emails={$emails}");
        return self::SUCCESS;
    }
}
