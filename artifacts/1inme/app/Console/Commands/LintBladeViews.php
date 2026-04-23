<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class LintBladeViews extends Command
{
    protected $signature = 'view:lint {--path=* : Extra view paths to scan in addition to resources/views} {--jobs=8 : Parallel php -l processes}';

    protected $description = 'Compile every Blade view and run php -l on the result to catch syntax errors before deploy';

    public function handle(): int
    {
        $paths = array_merge(
            [resource_path('views')],
            (array) $this->option('path'),
        );

        $files = [];
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        if (empty($files)) {
            $this->warn('No Blade views found to lint.');

            return self::SUCCESS;
        }

        $compiler = app('blade.compiler');
        $tmpDir = storage_path('framework/blade-lint');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        // Stage 1: compile every blade file to a temp .php file. Compilation
        // failures are recorded immediately.
        $jobs = [];
        $failures = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            try {
                $compiled = $compiler->compileString($source);
            } catch (\Throwable $e) {
                $failures[] = [
                    'file' => $file,
                    'stage' => 'compile',
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            $tmp = tempnam($tmpDir, 'blade-lint-');
            if ($tmp === false) {
                $failures[] = [
                    'file' => $file,
                    'stage' => 'tempfile',
                    'message' => 'Could not allocate a temp file in '.$tmpDir,
                ];
                continue;
            }
            // php -l doesn't care about extension; using the tempnam path
            // directly avoids leaking the original handle on disk.
            file_put_contents($tmp, $compiled);
            $jobs[] = ['file' => $file, 'tmp' => $tmp];
        }

        // Stage 2: php -l in parallel. The Symfony Process layer lets us run a
        // bounded pool so even thousands of views finish quickly.
        $maxParallel = max(1, (int) $this->option('jobs'));
        $running = []; // index => ['proc' => Process, 'job' => ...]
        $cursor = 0;
        $checked = count($jobs);

        while ($cursor < count($jobs) || ! empty($running)) {
            while (count($running) < $maxParallel && $cursor < count($jobs)) {
                $job = $jobs[$cursor];
                $proc = new Process([PHP_BINARY, '-l', $job['tmp']]);
                $proc->start();
                $running[$cursor] = ['proc' => $proc, 'job' => $job];
                $cursor++;
            }

            foreach ($running as $idx => $entry) {
                if (! $entry['proc']->isRunning()) {
                    $proc = $entry['proc'];
                    $job = $entry['job'];
                    if (! $proc->isSuccessful()) {
                        $output = trim($proc->getOutput().$proc->getErrorOutput());
                        $failures[] = [
                            'file' => $job['file'],
                            'stage' => 'php -l',
                            'message' => str_replace($job['tmp'], '<compiled>', $output),
                        ];
                    }
                    @unlink($job['tmp']);
                    unset($running[$idx]);
                }
            }

            if (! empty($running)) {
                usleep(10000);
            }
        }

        if (! empty($failures)) {
            $this->error(sprintf('Blade lint failed for %d of %d view(s):', count($failures), $checked));
            foreach ($failures as $failure) {
                $this->line('');
                $this->line('  '.$failure['file']);
                $this->line('  ['.$failure['stage'].'] '.$failure['message']);
            }

            return self::FAILURE;
        }

        $this->info(sprintf('All %d Blade view(s) compiled and linted clean.', $checked));

        return self::SUCCESS;
    }
}
