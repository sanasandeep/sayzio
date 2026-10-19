<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Compiles every Blade template and runs `php -l` on the compiled output.
 *
 * Catches template-level syntax errors (e.g. @json on inline arrays containing
 * commas, malformed directives, unbalanced braces) before they reach a real
 * request and crash at runtime.
 *
 * ---- Why this no longer shells out --------------------------------------
 *
 * The lint pass used to be one shell pipeline:
 *
 *     cat list | xargs -r -d "\n" -n 1 -P 8 sh -c '...'
 *
 * Both -r and -d are GNU extensions. macOS ships BSD xargs, which has
 * neither, so on a Mac the pipeline never ran a single `php -l`. It printed
 *
 *     xargs: invalid option -- d
 *
 * and this test dutifully reported that usage dump as "Blade templates
 * produced PHP syntax errors". GitHub's Ubuntu runner has GNU xargs, so CI
 * stayed green and the breakage showed only on the machine where the
 * templates are actually written -- the one place the guard was most needed.
 *
 * It now runs php -l through Symfony's Process in a small pool: no shell, no
 * quoting, no platform-specific flags, and each failure is attributed to the
 * process that produced it rather than by matching paths in merged output.
 *
 * Run locally:
 *   php artisan test --filter=BladeViewsCompileTest
 */
class BladeViewsCompileTest extends TestCase
{
    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $viewsPath = resource_path('views');
        $this->assertDirectoryExists($viewsPath, "resources/views directory not found");

        $finder = (new Finder())
            ->files()
            ->in($viewsPath)
            ->name('*.blade.php');

        $tmpDir = sys_get_temp_dir() . '/blade-lint-' . getmypid();
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $failures = [];
        $checked = 0;
        // Map compiled tmp path -> original blade relative path so we can
        // surface a friendly failure message after the parallel lint pass.
        $tmpToRelative = [];

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $source = file_get_contents($file->getPathname());

            try {
                $compiled = Blade::compileString($source);
            } catch (\Throwable $e) {
                $failures[] = sprintf("%s: blade compile threw %s: %s", $relative, get_class($e), $e->getMessage());
                continue;
            }

            $tmpFile = $tmpDir . '/' . md5($relative) . '.php';
            file_put_contents($tmpFile, $compiled);
            $tmpToRelative[$tmpFile] = $relative;
            $checked++;
        }

        // Lint the compiled files in parallel. php -l is one fork per file,
        // so a small pool keeps 400+ templates to a couple of seconds.
        if (! empty($tmpToRelative)) {
            foreach ($this->lintCompiled($tmpToRelative) as $failure) {
                $failures[] = $failure;
            }

            foreach (array_keys($tmpToRelative) as $tmp) {
                @unlink($tmp);
            }
        }

        @rmdir($tmpDir);

        $this->assertGreaterThan(0, $checked, 'No Blade views were found to lint.');

        if (! empty($failures)) {
            $this->fail(
                "Blade templates produced PHP syntax errors after compilation:\n\n"
                . implode("\n\n", $failures)
            );
        }
    }

    /**
     * The guard stayed blind for as long as it did because nothing checked the
     * checker: a lint pass that runs no linter reports no errors, and reads
     * exactly like a lint pass that found none.
     *
     * So hand the linter a file that is definitely broken and require it to
     * say so, under the Blade view's own name. If the pool ever stops running
     * php -l -- another platform flag, a missing binary, a process that fails
     * to start -- this fails instead of going quiet.
     */
    public function test_the_linter_reports_a_broken_template(): void
    {
        $tmpDir = sys_get_temp_dir() . '/blade-lint-selfcheck-' . getmypid();
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $broken = $tmpDir . '/broken.php';
        $sound  = $tmpDir . '/sound.php';
        file_put_contents($broken, "<?php if (true) { echo 'no closing brace';\n");
        file_put_contents($sound, "<?php echo 'fine';\n");

        $failures = $this->lintCompiled([
            $broken => 'pretend/broken.blade.php',
            $sound  => 'pretend/sound.blade.php',
        ]);

        @unlink($broken);
        @unlink($sound);
        @rmdir($tmpDir);

        $this->assertCount(
            1,
            $failures,
            'the linter must report the broken file and only the broken file'
        );
        $this->assertStringContainsString(
            'pretend/broken.blade.php',
            $failures[0],
            'a lint failure must name the Blade view, not the compiled temp file'
        );
        $this->assertStringNotContainsString(
            $broken,
            $failures[0],
            'the temp path should have been swapped for the view name'
        );
    }

    /**
     * Run php -l over the compiled files, up to BLADE_LINT_PARALLELISM at a
     * time, and return one human-readable failure per file that did not pass.
     *
     * @param  array<string,string>  $tmpToRelative  compiled path => blade path
     * @return list<string>
     */
    private function lintCompiled(array $tmpToRelative): array
    {
        $php = (defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : 'php';
        $procs = max(1, (int) (getenv('BLADE_LINT_PARALLELISM') ?: 8));

        $queue = array_keys($tmpToRelative);
        $running = [];
        $failures = [];

        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $procs) {
                $tmp = array_shift($queue);
                $process = new Process([$php, '-l', $tmp]);
                $process->start();
                $running[$tmp] = $process;
            }

            usleep(2000);

            foreach ($running as $tmp => $process) {
                if ($process->isRunning()) {
                    continue;
                }
                unset($running[$tmp]);

                if ($process->isSuccessful()) {
                    continue;
                }

                $relative = $tmpToRelative[$tmp];
                $report = trim($process->getOutput() . "\n" . $process->getErrorOutput());
                $report = implode(' ', array_filter(
                    array_map('trim', explode("\n", $report)),
                    fn ($line) => $line !== '' && ! str_starts_with($line, 'Errors parsing')
                ));

                $failures[] = $relative . ': ' . str_replace($tmp, "<compiled:{$relative}>", $report);
            }
        }

        sort($failures);

        return $failures;
    }
}
