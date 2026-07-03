<?php

namespace App\Console\Commands;

use App\Modules\Common\Middleware\BlockReadonlyDemoWrites as Guard;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

/**
 * Drift guard for the read-only demo write-allowlist in
 * {@see \App\Modules\Common\Middleware\BlockReadonlyDemoWrites}.
 *
 * Problem it prevents: that allowlist is hand-maintained. Every time a new
 * interactive-but-non-persisting POST feature ships (an AI preview, a cost
 * `.estimate`, a `.suggest`/`.think`, a `lookup`, a `.preview*` render, a
 * `generate-art` render, a `quote`), a developer must remember to add it — or
 * demo visitors silently get a wrong "changes aren't saved" banner on a
 * feature that never saved anything. Nothing used to flag that, so the list
 * rotted as the app grew.
 *
 * This command closes the loop. It scans every registered write route
 * (POST/PUT/PATCH/DELETE) whose route-name OR URI ends in one of the
 * interactive verb suffixes ({@see Guard::INTERACTIVE_VERB_SUFFIXES}) — or in a
 * segment starting with "preview" — and fails when the route is NEITHER:
 *   - allowed for the demo ({@see Guard::ALLOWED_ROUTE_NAMES},
 *     {@see Guard::ALLOWED_INTERACTIVE_ROUTE_NAMES},
 *     {@see Guard::ALLOWED_PATHS}, {@see Guard::ALLOWED_INTERACTIVE_PATHS}), NOR
 *   - consciously acknowledged as interactive-looking-but-persisting and thus
 *     intentionally blocked ({@see Guard::ACKNOWLEDGED_NONALLOWED_ROUTE_NAMES},
 *     {@see Guard::ACKNOWLEDGED_NONALLOWED_PATHS}).
 *
 * It also flags STALE entries — allowlisted/acknowledged names or path patterns
 * that no longer match any registered write route — so the lists don't
 * accumulate dead references.
 *
 * Admin routes (admin guard, never reachable by the demo persona) are excluded.
 * No database is required — it inspects the in-memory route table only, so it
 * runs as a fast pre-merge validation step.
 *
 * Exit codes:
 *   0 — every interactive write route is classified and no entry is stale.
 *   1 — drift: an unclassified interactive route and/or a stale list entry.
 */
class CheckDemoAllowlist extends Command
{
    protected $signature = 'demo:check-allowlist';

    protected $description = 'Fail when an interactive (non-persisting) write route is missing from the read-only demo allowlist.';

    public function handle(): int
    {
        $classifiedNames = array_merge(
            Guard::ALLOWED_ROUTE_NAMES,
            Guard::ALLOWED_INTERACTIVE_ROUTE_NAMES,
            Guard::ACKNOWLEDGED_NONALLOWED_ROUTE_NAMES,
        );
        $classifiedPaths = array_merge(
            Guard::ALLOWED_PATHS,
            Guard::ALLOWED_INTERACTIVE_PATHS,
            Guard::ACKNOWLEDGED_NONALLOWED_PATHS,
        );

        // Entries we hold responsible for staying in sync (auth entries are
        // stable and excluded from staleness checks).
        $trackedNames = array_merge(
            Guard::ALLOWED_INTERACTIVE_ROUTE_NAMES,
            Guard::ACKNOWLEDGED_NONALLOWED_ROUTE_NAMES,
        );
        $trackedPaths = array_merge(
            Guard::ALLOWED_INTERACTIVE_PATHS,
            Guard::ACKNOWLEDGED_NONALLOWED_PATHS,
        );

        $drift = [];
        $allWriteNames = [];
        $allWriteUris = [];
        $scanned = 0;

        foreach (Route::getRoutes() as $route) {
            $write = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
            if (empty($write)) {
                continue;
            }

            $uri = $route->uri();
            $name = $route->getName();

            if ($this->isAdmin($name, $uri)) {
                continue;
            }

            $concrete = $this->concreteUri($uri);

            // Record every non-admin write route so staleness is checked against
            // the full write surface — an allowlist entry may name a legitimately
            // non-persisting route (e.g. a QR image `download`, or the unified
            // `cost-estimate`) whose last segment the interactive-verb heuristic
            // below does not flag, yet it is a real, current route.
            if ($name !== null) {
                $allWriteNames[$name] = true;
            }
            $allWriteUris[] = $concrete;

            if (! $this->isInteractive($name, $uri)) {
                continue;
            }

            $scanned++;

            $nameHit = $name !== null && in_array($name, $classifiedNames, true);
            $pathHit = false;
            foreach ($classifiedPaths as $pattern) {
                if (Str::is($pattern, $concrete)) {
                    $pathHit = true;
                }
            }

            if (! $nameHit && ! $pathHit) {
                $drift[] = [
                    'methods' => implode(',', $write),
                    'name' => $name ?? '(unnamed)',
                    'uri' => $uri,
                ];
            }
        }

        // Stale = a tracked entry that matches no registered write route at all
        // (renamed or deleted), independent of the interactive-verb heuristic so
        // that non-verb allowlist entries are validated, not falsely flagged.
        $staleNames = [];
        foreach ($trackedNames as $trackedName) {
            if (! isset($allWriteNames[$trackedName])) {
                $staleNames[] = $trackedName;
            }
        }
        $stalePaths = [];
        foreach ($trackedPaths as $trackedPath) {
            $matched = false;
            foreach ($allWriteUris as $writeUri) {
                if (Str::is($trackedPath, $writeUri)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $stalePaths[] = $trackedPath;
            }
        }

        if (empty($drift) && empty($staleNames) && empty($stalePaths)) {
            $this->info("OK — scanned {$scanned} interactive write route(s); all are classified in the demo allowlist and no entry is stale.");

            return self::SUCCESS;
        }

        if (! empty($drift)) {
            $this->error('Demo allowlist drift — ' . count($drift) . ' interactive write route(s) are neither allowed nor acknowledged:');
            $this->newLine();
            foreach ($drift as $d) {
                $this->line("  <fg=yellow>[{$d['methods']}]</> {$d['name']}");
                $this->line("    {$d['uri']}");
            }
            $this->newLine();
            $this->line('If it persists NOTHING, add it to ALLOWED_INTERACTIVE_ROUTE_NAMES (named) or');
            $this->line('ALLOWED_INTERACTIVE_PATHS (unnamed API) in BlockReadonlyDemoWrites so demo');
            $this->line('visitors can use it. If it DOES persist despite the interactive-looking name,');
            $this->line('add it to the matching ACKNOWLEDGED_NONALLOWED_* list so it stays blocked.');
            $this->newLine();
        }

        if (! empty($staleNames) || ! empty($stalePaths)) {
            $this->error('Demo allowlist has stale entries that match no registered write route:');
            foreach ($staleNames as $n) {
                $this->line("  <fg=yellow>name</> {$n}");
            }
            foreach ($stalePaths as $p) {
                $this->line("  <fg=yellow>path</> {$p}");
            }
            $this->line('Remove them from BlockReadonlyDemoWrites — the route was renamed or deleted.');
            $this->newLine();
        }

        return self::FAILURE;
    }

    /** Admin surfaces live behind the admin guard; the demo persona never reaches them. */
    private function isAdmin(?string $name, string $uri): bool
    {
        return ($name !== null && str_starts_with($name, 'admin.'))
            || str_starts_with($uri, 'admin/')
            || str_starts_with($uri, 'api/v1/admin/');
    }

    /**
     * A route is "interactive" when the LAST segment of its name or URI is a
     * known interactive verb, or starts with "preview". Only the last segment
     * is considered so that persisting endpoints nested under a preview segment
     * (e.g. contacts/import/preview/{token}/confirm) are not falsely matched.
     */
    private function isInteractive(?string $name, string $uri): bool
    {
        if ($name !== null && $this->segmentIsInteractive($this->lastSegment($name, '.'))) {
            return true;
        }

        return $this->segmentIsInteractive($this->lastSegment($uri, '/'));
    }

    private function segmentIsInteractive(string $segment): bool
    {
        return str_starts_with($segment, 'preview')
            || in_array($segment, Guard::INTERACTIVE_VERB_SUFFIXES, true);
    }

    private function lastSegment(string $value, string $delimiter): string
    {
        $pos = strrpos($value, $delimiter);

        return $pos === false ? $value : substr($value, $pos + 1);
    }

    /** Replace {param} placeholders with a concrete token so Str::is() can match path patterns. */
    private function concreteUri(string $uri): string
    {
        return preg_replace('/\{[^}]+\}/', '1', $uri) ?? $uri;
    }
}
