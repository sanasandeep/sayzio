<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Meta-guard: proves that `scripts/check-query-columns.php` itself still
 * catches the regression it exists to catch.
 *
 * The query-columns guard fails CI when a query-builder call references a
 * literal column that does not exist — the class of bug that shipped the
 * biolink link-picker querying `links.meta_title` (real column: `seo_title`)
 * and 500'd in production until an e2e test caught it. The guard is a static
 * token scanner with several moving parts (model discovery, SchemaManifest
 * wiring, rooted-chain following, alias learning). If a refactor broke any of
 * them the guard could go silently green on everything. This test runs the
 * guard as a subprocess against throwaway fixtures and asserts it still
 * distinguishes clean call sites from dead-column ones — including the exact
 * historical `meta_title` pattern.
 */
class CheckQueryColumnsGuardTest extends TestCase
{
    private string $projectRoot;

    private string $scriptPath;

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 3);
        $this->scriptPath = $this->projectRoot.'/scripts/check-query-columns.php';

        $this->assertFileExists($this->scriptPath, 'The query-columns guard script is missing.');

        $this->tmpDir = sys_get_temp_dir().'/query-guard-test-'.bin2hex(random_bytes(6));
        if (! mkdir($this->tmpDir, 0777, true) && ! is_dir($this->tmpDir)) {
            $this->fail("Could not create temp dir {$this->tmpDir}");
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpDir);

        parent::tearDown();
    }

    public function test_clean_call_sites_and_raw_aliases_pass(): void
    {
        $scanDir = $this->makeScanDir('clean', <<<'PHP'
            <?php
            use App\Modules\User\Models\Link;
            // Real columns on a rooted chain.
            $rows = Link::where('user_id', 1)
                ->where('is_active', true)
                ->select(['id', 'alias', 'seo_title'])
                ->orderByDesc('created_at')
                ->get();
            // Raw-SQL alias referenced later in the same file must be exempt.
            $top = Link::where('user_id', 1)
                ->selectRaw('type, count(*) as cnt')
                ->groupBy('type')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'type');
            // JSON path — only the base column is checked.
            $x = Link::whereNotNull('settings->event_category')->count();
            PHP);

        $result = $this->runGuard([$scanDir]);

        $this->assertSame(
            0,
            $result['exit'],
            "Guard should PASS on clean rooted chains, raw aliases, and JSON paths.\n".$result['stderr']
        );
        $this->assertStringContainsString('OK:', $result['stderr']);
    }

    public function test_historical_meta_title_pattern_fails(): void
    {
        // The exact bug this guard exists for: links has seo_title, NOT
        // meta_title. Note `meta_title` IS a real column of another table
        // (blogs), so only the ROOTED tier can catch this — proving the
        // rooted-chain precision works, not just a global union check.
        $scanDir = $this->makeScanDir('meta-title', <<<'PHP'
            <?php
            use App\Modules\User\Models\Link;
            $links = Link::where('user_id', 1)
                ->select(['id', 'alias', 'meta_title'])
                ->orderBy('created_at', 'desc')
                ->get();
            PHP);

        $result = $this->runGuard([$scanDir]);

        $this->assertSame(
            1,
            $result['exit'],
            "Guard should FAIL on the historical links.meta_title pattern.\n".$result['stderr']
        );
        $this->assertStringContainsString('meta_title', $result['stderr'], 'Failure output should name the dead column.');
        $this->assertStringContainsString('links', $result['stderr'], 'Failure output should name the table.');
    }

    public function test_qualified_and_union_dead_columns_fail(): void
    {
        $scanDir = $this->makeScanDir('dead-misc', <<<'PHP'
            <?php
            // Tier 2 — qualified literal on an arbitrary receiver.
            $q1 = $query->where('links.definitely_not_a_links_column', 1);
            // Tier 3 — union check: a column that exists on NO table, on a
            // collection-safe builder method.
            $q2 = $builder->orderByDesc('column_that_exists_nowhere_at_all');
            PHP);

        $result = $this->runGuard([$scanDir]);

        $this->assertSame(1, $result['exit'], "Guard should FAIL on qualified/union dead columns.\n".$result['stderr']);
        $this->assertStringContainsString('definitely_not_a_links_column', $result['stderr']);
        $this->assertStringContainsString('column_that_exists_nowhere_at_all', $result['stderr']);
    }

    public function test_collection_methods_and_broken_chains_are_not_flagged(): void
    {
        $scanDir = $this->makeScanDir('safe', <<<'PHP'
            <?php
            use App\Modules\User\Models\Link;
            // Collection ->where on payload keys must never be union-checked.
            $item = collect($payload)->where('some_arbitrary_payload_key', 'x')->first();
            // After a join the builder sees other tables — chain checking stops.
            $rows = Link::query()
                ->join('link_clicks', 'link_clicks.link_id', '=', 'links.id')
                ->where('clicked_at', '>', now())
                ->get();
            // withCount alias.
            $top = Link::withCount('clicks')->orderByDesc('clicks_count')->get();
            PHP);

        $result = $this->runGuard([$scanDir]);

        $this->assertSame(
            0,
            $result['exit'],
            "Guard must not flag collection methods, post-join columns, or *_count aliases.\n".$result['stderr']
        );
    }

    private function makeScanDir(string $name, string $php): string
    {
        $dir = $this->tmpDir.'/scan-'.$name;
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/CallSite.php', $php."\n");

        return $dir;
    }

    /**
     * @param  array<int,string>  $scanDirs
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGuard(array $scanDirs): array
    {
        $process = new Process(
            array_merge([PHP_BINARY, $this->scriptPath], $scanDirs),
            $this->projectRoot
        );
        $process->setTimeout(120);
        $process->run();

        return [
            'exit'   => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
