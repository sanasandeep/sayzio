<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Compiles every Blade template and runs `php -l` on the compiled output.
 *
 * Catches template-level syntax errors (e.g. @json on inline arrays containing
 * commas, malformed directives, unbalanced braces) before they reach a real
 * request and crash at runtime.
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

        $php = (defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : 'php';
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

        // Lint all compiled files in parallel via xargs -P. Much faster than
        // forking `php -l` once per file sequentially (408 templates).
        if (! empty($tmpToRelative)) {
            $listFile = $tmpDir . '/_files.txt';
            file_put_contents($listFile, implode("\n", array_keys($tmpToRelative)));

            $procs = (int) (getenv('BLADE_LINT_PARALLELISM') ?: 8);
            // Wrap `php -l` in `sh -c` so xargs doesn't abort on the first
            // failing file (php -l exits 255 on parse errors). We want every
            // broken view reported in a single pass.
            $cmd = sprintf(
                'cat %s | xargs -r -d "\n" -n 1 -P %d sh -c %s _ 2>&1',
                escapeshellarg($listFile),
                $procs,
                escapeshellarg(escapeshellarg($php) . ' -l "$1"; exit 0')
            );
            $output = (string) shell_exec($cmd);

            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, 'No syntax errors detected')) {
                    continue;
                }
                // PHP lint error lines reference the temp file path; map it
                // back to the originating blade view for readability.
                foreach ($tmpToRelative as $tmp => $relative) {
                    if (strpos($line, $tmp) !== false) {
                        $failures[] = $relative . ': ' . str_replace($tmp, "<compiled:{$relative}>", $line);
                        continue 2;
                    }
                }
                // Unmatched diagnostic line — surface it verbatim so we don't
                // silently swallow lint output we didn't recognise.
                $failures[] = $line;
            }

            foreach (array_keys($tmpToRelative) as $tmp) {
                @unlink($tmp);
            }
            @unlink($listFile);
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
}
