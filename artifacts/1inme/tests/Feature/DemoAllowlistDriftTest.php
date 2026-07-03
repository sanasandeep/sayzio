<?php

namespace Tests\Feature;

use App\Modules\Common\Middleware\BlockReadonlyDemoWrites as Guard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * In-suite mirror of the `demo:check-allowlist` drift guard
 * ({@see \App\Console\Commands\CheckDemoAllowlist}).
 *
 * The read-only demo write-allowlist in {@see Guard} is hand-maintained: every
 * new interactive-but-non-persisting POST feature (an AI preview, a cost
 * `.estimate`, a `.suggest`/`.think`, a `lookup`, a `.preview*` render, a
 * `generate-art`, a `quote`) must be classified there, or demo visitors get a
 * wrong "changes aren't saved" banner on a feature that never saved anything.
 *
 * This asserts the whole route table is in sync so the drift is caught in the
 * regular test run too, not only by the standalone validation command. It needs
 * no database — it inspects the in-memory route table only.
 */
class DemoAllowlistDriftTest extends TestCase
{
    public function test_demo_check_allowlist_command_passes(): void
    {
        $this->artisan('demo:check-allowlist')->assertExitCode(0);
    }

    public function test_every_interactive_write_route_is_classified(): void
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

        $unclassified = [];

        foreach (Route::getRoutes() as $route) {
            if (empty(array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']))) {
                continue;
            }

            $uri = $route->uri();
            $name = $route->getName();

            // Admin surfaces live behind the admin guard; the demo persona
            // never reaches them.
            if (($name !== null && str_starts_with($name, 'admin.'))
                || str_starts_with($uri, 'admin/')
                || str_starts_with($uri, 'api/v1/admin/')) {
                continue;
            }

            if (! $this->isInteractive($name, $uri)) {
                continue;
            }

            $nameHit = $name !== null && in_array($name, $classifiedNames, true);
            $concrete = preg_replace('/\{[^}]+\}/', '1', $uri) ?? $uri;
            $pathHit = false;
            foreach ($classifiedPaths as $pattern) {
                if (Str::is($pattern, $concrete)) {
                    $pathHit = true;
                    break;
                }
            }

            if (! $nameHit && ! $pathHit) {
                $unclassified[] = ($name ?? '(unnamed)') . ' [' . $uri . ']';
            }
        }

        $this->assertSame(
            [],
            $unclassified,
            "Interactive write route(s) missing from the demo allowlist in BlockReadonlyDemoWrites:\n"
                . implode("\n", $unclassified)
        );
    }

    private function isInteractive(?string $name, string $uri): bool
    {
        $lastName = $name !== null ? $this->lastSegment($name, '.') : '';
        $lastUri = $this->lastSegment($uri, '/');

        foreach ([$lastName, $lastUri] as $segment) {
            if ($segment !== '' && (
                str_starts_with($segment, 'preview')
                || in_array($segment, Guard::INTERACTIVE_VERB_SUFFIXES, true)
            )) {
                return true;
            }
        }

        return false;
    }

    private function lastSegment(string $value, string $delimiter): string
    {
        $pos = strrpos($value, $delimiter);

        return $pos === false ? $value : substr($value, $pos + 1);
    }
}
