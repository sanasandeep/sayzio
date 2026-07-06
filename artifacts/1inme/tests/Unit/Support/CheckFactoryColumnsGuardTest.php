<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Meta-guard: proves that `scripts/check-factory-columns.php` itself still
 * catches the regression it exists to catch.
 *
 * The factory-columns guard fails CI when a `<Model>::factory(...)` call site
 * forwards an attribute key that is not a real DB column — a class of failure
 * that once produced 50+ test failures. But the guard is a static token
 * scanner with several moving parts (factory discovery, SchemaManifest wiring,
 * the token walker). If a future refactor broke any of them the guard could
 * start passing on everything — a false green — and the protection would be
 * silently lost. This test invokes the guard against tiny throwaway fixtures
 * and asserts it still distinguishes a clean call site from a dead-key one,
 * and that its factory discovery is not hard-coded to `User`.
 */
class CheckFactoryColumnsGuardTest extends TestCase
{
    private string $projectRoot;

    private string $scriptPath;

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 3);
        $this->scriptPath = $this->projectRoot.'/scripts/check-factory-columns.php';

        $this->assertFileExists($this->scriptPath, 'The factory-columns guard script is missing.');

        $this->tmpDir = sys_get_temp_dir().'/factory-guard-test-'.bin2hex(random_bytes(6));
        if (! mkdir($this->tmpDir, 0777, true) && ! is_dir($this->tmpDir)) {
            $this->fail("Could not create temp dir {$this->tmpDir}");
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpDir);

        parent::tearDown();
    }

    public function test_clean_call_sites_pass_and_discovery_is_generalized(): void
    {
        // A throwaway factory for a real, non-User model (Link) fed via the
        // extra-factory-dir seam. If discovery regressed to hard-coding "User"
        // this factory would never be found, so the "2 model factory/factories"
        // banner would not appear.
        $factoryDir = $this->makeFixtureLinkFactoryDir();

        $scanDir = $this->makeScanDir('clean', <<<'PHP'
            <?php
            use App\Modules\User\Models\Link;
            use App\Modules\User\Models\User;
            User::factory()->create(['name' => 'A', 'email' => 'a@ex.com']);
            Link::factory()->create(['alias' => 'a']);
            PHP);

        $result = $this->runGuard([$scanDir], $factoryDir);

        $this->assertSame(
            0,
            $result['exit'],
            "Guard should PASS on clean User::factory() / Link::factory() calls.\n".$result['stderr']
        );
        $this->assertMatchesRegularExpression(
            '/\b2\s+model\s+factor(?:y|ies)/i',
            $result['stderr'],
            'Guard should discover two factories (User + fixture Link), proving discovery is generalized, not hard-coded to User.'
        );
        $this->assertStringContainsString('Link', $result['stderr']);
    }

    public function test_dead_column_keys_fail_for_every_discovered_factory(): void
    {
        // Fixture Link factory so a dead key on a NON-User factory is exercised
        // in the same run as a dead key on User, proving the dead-column scan is
        // applied to every discovered factory, not just User.
        $factoryDir = $this->makeFixtureLinkFactoryDir();

        $scanDir = $this->makeScanDir('dead', <<<'PHP'
            <?php
            use App\Modules\User\Models\Link;
            use App\Modules\User\Models\User;
            User::factory()->create([
                'name' => 'A',
                'definitely_not_a_real_users_column' => 'x',
            ]);
            Link::factory()->create(['not_a_real_links_column' => 'x']);
            PHP);

        $result = $this->runGuard([$scanDir], $factoryDir);

        $this->assertSame(
            1,
            $result['exit'],
            "Guard should FAIL when a dead column key is forwarded to a factory.\n".$result['stderr']
        );
        $this->assertStringContainsString(
            'definitely_not_a_real_users_column',
            $result['stderr'],
            'The failure output should name the dead User key.'
        );
        $this->assertStringContainsString(
            'not_a_real_links_column',
            $result['stderr'],
            'The dead-column scan must apply to the discovered non-User factory too.'
        );
    }

    /**
     * Write a throwaway `Database\Factories\FixtureLinkFactory` (for the real
     * Link model) into its own directory and return the directory path, ready
     * to feed the guard via CHECK_FACTORY_COLUMNS_EXTRA_FACTORY_DIRS.
     */
    private function makeFixtureLinkFactoryDir(): string
    {
        $factoryDir = $this->tmpDir.'/factories';
        mkdir($factoryDir, 0777, true);
        file_put_contents($factoryDir.'/FixtureLinkFactory.php', <<<'PHP'
            <?php
            namespace Database\Factories;
            use App\Modules\User\Models\Link;
            use Illuminate\Database\Eloquent\Factories\Factory;
            class FixtureLinkFactory extends Factory
            {
                protected $model = Link::class;
                public function definition(): array { return []; }
            }
            PHP);

        return $factoryDir;
    }

    /**
     * Write a single-file scan fixture into its own directory and return the
     * directory path (the guard scans directories, not files).
     */
    private function makeScanDir(string $name, string $php): string
    {
        $dir = $this->tmpDir.'/scan-'.$name;
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/CallSite.php', $php."\n");

        return $dir;
    }

    /**
     * Invoke the guard as a subprocess against the given scan dirs, optionally
     * appending an extra factory directory via the env seam.
     *
     * @param  array<int,string>  $scanDirs
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGuard(array $scanDirs, ?string $extraFactoryDir = null): array
    {
        $command = array_merge([PHP_BINARY, $this->scriptPath], $scanDirs);

        $env = null;
        if ($extraFactoryDir !== null) {
            $env = ['CHECK_FACTORY_COLUMNS_EXTRA_FACTORY_DIRS' => $extraFactoryDir];
        }

        $process = new Process($command, $this->projectRoot, $env);
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
