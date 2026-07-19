<?php

namespace App\Console\Commands;

use App\Services\Integrations\GitHubTokenHealth;
use Illuminate\Console\Command;

/**
 * Scheduled safety net for the GitHub push credential (managed at
 * Admin > Integrations > GitHub Token; GITHUB_TOKEN env secret as fallback).
 *
 * Code is mirrored to GitHub after each publish using a fine-grained
 * personal access token that expires (~90-day lifetime). When it dies,
 * every push fails with an auth error and the repo silently drifts behind
 * the workspace. This daily command makes a lightweight authenticated
 * GitHub API call, warns ops admins when the token is missing, rejected,
 * or within the expiry warning window, and sends an all-clear once a
 * renewed token works again. Dedup/cooldown state lives in app_settings
 * under `github_token_health` (see GitHubTokenHealth).
 */
class CheckGitHubToken extends Command
{
    protected $signature = 'github:check-token
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Verify the GitHub push token still authenticates and alert admins before it expires or when pushes start failing.';

    public function handle(): int
    {
        $result = GitHubTokenHealth::check((bool) $this->option('force'));

        match ($result['status']) {
            'ok'           => $this->info($result['detail']),
            'inconclusive' => $this->warn('Inconclusive — ' . $result['detail']),
            default        => $this->error($result['detail']),
        };

        $this->info(match ($result['action']) {
            'alert_sent'    => 'Admin alert dispatched (in-app + email).',
            'recovery_sent' => 'Recovery all-clear dispatched to admins.',
            'cooldown'      => 'Within cooldown window — not re-sending (use --force to override).',
            default         => 'No alert action taken.',
        });

        return self::SUCCESS;
    }
}
