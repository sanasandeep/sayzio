<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detects whether the GitHub repo has commits newer than the running
 * deployment and dispatches the existing "Deploy to EC2" GitHub Actions
 * workflow on demand.
 *
 * Detection:
 *   - Local commit  → git rev-parse HEAD on APP_DIR (or the repo root)
 *   - Remote commit → GitHub REST API (repos/{owner}/{repo}/commits/main)
 *   If the two SHAs differ the app is "behind" and surfacess the banner.
 *
 * Deploy:
 *   - Uses workflow_dispatch on deploy-ec2.yml via the GitHub API
 *   - Protected by a 30-second cache lock so double-clicks are a no-op
 *   - Audit record persisted in app_settings under system_update.last_deploy
 *
 * Environment awareness:
 *   - On Replit (REPL_ID present) the feature is always hidden / "managed"
 *   - On EC2 the full feature is shown
 *
 * The GitHub token (GITHUB_TOKEN / services.github.token) and repo
 * (GITHUB_REPO / services.github.repo) are the same credentials already
 * used by the GitHub push-mirroring feature and GitHubTokenHealth.
 * The token must include the `actions:write` (or `workflow`) scope for the
 * workflow_dispatch call to succeed; a push-only fine-grained token without
 * that scope will get a 422 on the dispatch but the update-check still works.
 */
class SystemUpdateService
{
    public const CACHE_KEY          = 'system_update.status';
    public const CACHE_TTL          = 300; // 5 minutes
    public const DEPLOY_LOCK_KEY    = 'system_update.deploy_lock';
    public const DEPLOY_FLAG_KEY    = 'system_update.deploying';   // simple bool flag (TTL-bounded)
    public const DEPLOY_LOCK_TTL    = 1800; // 30 minutes (covers a full deploy cycle)
    public const AUDIT_KEY          = 'system_update.last_deploy';
    public const WORKFLOW_FILE      = 'deploy-ec2.yml';

    // ─────────────────────────────────────────────────────────────
    // Environment
    // ─────────────────────────────────────────────────────────────

    /**
     * Running on Replit? Detected via the REPL_ID environment variable
     * that Replit injects into every container. On EC2 this is absent.
     */
    public static function isReplit(): bool
    {
        return !empty(env('REPL_ID', ''));
    }

    /**
     * Is the feature configured? Requires a GitHub token and a repo slug.
     */
    public static function isConfigured(): bool
    {
        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo',  '');
        return $token !== '' && $repo !== '';
    }

    // ─────────────────────────────────────────────────────────────
    // Update check
    // ─────────────────────────────────────────────────────────────

    /**
     * Cached check (up to 5 minutes). Always returns a shape-stable array.
     *
     * @return array{
     *   available:bool,
     *   local_sha:?string,
     *   remote_sha:?string,
     *   remote_message:?string,
     *   remote_date:?string,
     *   remote_author:?string,
     *   commits_behind:?int,
     *   error:?string,
     *   checked_at:string
     * }
     */
    public static function cachedStatus(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => self::checkStatus());
    }

    /** Flush the cached status so the next read re-checks immediately. */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Live (uncached) update check.
     */
    public static function checkStatus(): array
    {
        $base = [
            'available'      => false,
            'local_sha'      => null,
            'remote_sha'     => null,
            'remote_message' => null,
            'remote_date'    => null,
            'remote_author'  => null,
            'commits_behind' => null,
            'error'          => null,
            'checked_at'     => now()->toIso8601String(),
        ];

        if (!self::isConfigured()) {
            return $base + ['error' => 'not_configured'];
        }

        $localSha = self::localCommitSha();
        if (!$localSha) {
            return $base + ['error' => 'local_git_unavailable'];
        }
        $base['local_sha'] = $localSha;

        $remote = self::fetchRemoteCommit();
        if (!$remote) {
            return $base + ['error' => 'github_api_error'];
        }

        $base['remote_sha']     = $remote['sha'];
        $base['remote_message'] = $remote['message'];
        $base['remote_date']    = $remote['date'];
        $base['remote_author']  = $remote['author'];

        if ($remote['sha'] === $localSha) {
            return $base;
        }

        // Behind — count commits
        $base['available']      = true;
        $base['commits_behind'] = self::countCommitsBehind($localSha, $remote['sha']);
        return $base;
    }

    // ─────────────────────────────────────────────────────────────
    // Deploy trigger
    // ─────────────────────────────────────────────────────────────

    /**
     * Dispatch the "Deploy to EC2" GitHub Actions workflow via workflow_dispatch.
     *
     * Returns ['ok'=>true] or ['ok'=>false, 'error'=>'...'].
     * Guarded by a cache lock so only one deploy runs at a time.
     */
    public static function triggerDeploy(string $triggeredByEmail): array
    {
        // Acquire an atomic lock so only one dispatch can happen at a time.
        // The lock is held until the deploy flag TTL expires or is cleared.
        $lock = Cache::lock(self::DEPLOY_LOCK_KEY, self::DEPLOY_LOCK_TTL);

        if (!$lock->get()) {
            return ['ok' => false, 'error' => 'A deploy is already in progress. Please wait for it to finish.'];
        }

        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo', '');

        if (!$token || !$repo) {
            $lock->release();
            return ['ok' => false, 'error' => 'GitHub credentials are not configured.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization'        => 'Bearer ' . $token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->timeout(15)->post("https://api.github.com/repos/{$repo}/actions/workflows/" . self::WORKFLOW_FILE . '/dispatches', [
                'ref' => 'main',
            ]);

            if ($response->status() === 204) {
                // Store a simple TTL-bounded flag so any process can check progress
                // without trying to re-acquire the lock.
                Cache::put(self::DEPLOY_FLAG_KEY, true, self::DEPLOY_LOCK_TTL);
                self::recordAudit($triggeredByEmail, 'dispatched');
                self::flushCache();
                return ['ok' => true];
            }

            $lock->release();

            if ($response->status() === 422) {
                Log::warning('system-update: workflow_dispatch 422 — token may lack actions:write scope.', [
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'error' => 'The GitHub token does not have the `actions:write` or `workflow` scope required to dispatch workflows. Update the token or trigger the deploy from GitHub Actions.'];
            }

            return ['ok' => false, 'error' => "GitHub API returned HTTP {$response->status()}: " . $response->body()];
        } catch (\Throwable $e) {
            $lock->release();
            Log::error('system-update: dispatch failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Network error reaching GitHub: ' . $e->getMessage()];
        }
    }

    /** True while a deploy flag is set (i.e. a dispatch was recorded recently). */
    public static function isDeployInProgress(): bool
    {
        return Cache::has(self::DEPLOY_FLAG_KEY);
    }

    /** Clear the in-progress flag (called when the run completes). */
    public static function releaseDeployLock(): void
    {
        Cache::forget(self::DEPLOY_FLAG_KEY);
        Cache::lock(self::DEPLOY_LOCK_KEY)->forceRelease();
    }

    // ─────────────────────────────────────────────────────────────
    // GitHub Actions run status (for polling after dispatch)
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch the latest workflow_dispatch run for the deploy workflow.
     * Returns null when the token lacks actions:read or the run hasn't
     * appeared in GitHub's index yet (it can take a few seconds).
     *
     * @return array{id:int,status:string,conclusion:?string,html_url:string,created_at:string}|null
     */
    public static function latestDeployRun(): ?array
    {
        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo', '');
        if (!$token || !$repo) return null;

        try {
            $response = Http::withHeaders([
                'Authorization'        => 'Bearer ' . $token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->timeout(10)->get("https://api.github.com/repos/{$repo}/actions/workflows/" . self::WORKFLOW_FILE . '/runs', [
                'per_page' => 1,
            ]);

            if (!$response->ok()) return null;

            $runs = $response->json('workflow_runs', []);
            if (empty($runs)) return null;

            $r = $runs[0];
            return [
                'id'         => $r['id'],
                'status'     => $r['status'],      // queued | in_progress | completed
                'conclusion' => $r['conclusion'],  // success | failure | null
                'html_url'   => $r['html_url'],
                'created_at' => $r['created_at'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Audit record (stored in app_settings)
    // ─────────────────────────────────────────────────────────────

    public static function lastAudit(): ?array
    {
        $raw = AppSetting::get(self::AUDIT_KEY);
        if (!$raw || !is_string($raw)) return null;
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function recordAudit(string $email, string $status): void
    {
        AppSetting::put(self::AUDIT_KEY, json_encode([
            'triggered_by' => $email,
            'triggered_at' => now()->toIso8601String(),
            'status'       => $status,
        ]));
    }

    // ─────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────

    private static function localCommitSha(): ?string
    {
        // Prefer the app root; fall back to the git binary on PATH.
        $appDir = base_path('../..'); // artifacts/1inme/../../ → repo root
        $gitDir = realpath($appDir . '/.git');

        if ($gitDir) {
            // Fast path: read .git/HEAD → resolve to SHA without shelling out
            $head = @file_get_contents($gitDir . '/HEAD');
            if ($head && str_starts_with(trim($head), 'ref: ')) {
                $ref = trim(str_replace('ref: ', '', $head));
                $sha = @file_get_contents($gitDir . '/' . $ref);
                if ($sha && strlen(trim($sha)) === 40) {
                    return trim($sha);
                }
            }
        }

        // Shell fallback
        if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', (string) ini_get('disable_functions')))) {
            $sha = shell_exec('git -C ' . escapeshellarg(base_path('../..')) . ' rev-parse HEAD 2>/dev/null');
            if ($sha && strlen(trim($sha)) === 40) {
                return trim($sha);
            }
        }

        return null;
    }

    private static function fetchRemoteCommit(): ?array
    {
        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo', '');

        try {
            $response = Http::withHeaders([
                'Authorization'        => 'Bearer ' . $token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->timeout(10)->get("https://api.github.com/repos/{$repo}/commits/main");

            if (!$response->ok()) return null;

            $data = $response->json();
            return [
                'sha'     => $data['sha'] ?? null,
                'message' => \Illuminate\Support\Str::limit(
                    trim(explode("\n", $data['commit']['message'] ?? '')[0]),
                    120
                ),
                'date'   => $data['commit']['committer']['date'] ?? $data['commit']['author']['date'] ?? null,
                'author' => $data['commit']['author']['name'] ?? $data['author']['login'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('system-update: GitHub API fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Count how many commits on main come after $localSha.
     * Uses the GitHub compare API. Returns null on error.
     */
    private static function countCommitsBehind(string $localSha, string $remoteSha): ?int
    {
        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo', '');

        try {
            $response = Http::withHeaders([
                'Authorization'        => 'Bearer ' . $token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->timeout(10)->get("https://api.github.com/repos/{$repo}/compare/{$localSha}...{$remoteSha}");

            if (!$response->ok()) return null;
            return (int) ($response->json('ahead_by') ?? 0) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
