<?php

namespace App\Support\View;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * A Blade compiler that cannot 500 a page over who owns a compiled view.
 *
 * When a compiled view already exists and matches its template, Laravel
 * does not rewrite it -- it calls touch() to bump its timestamp. touch()
 * with an explicit time is only allowed for the file's OWNER. On the EC2
 * box the web server runs as `apache` and deploys run as `sayzio`, so any
 * view compiled by `sayzio` (a `php artisan view:cache`, a seeder that
 * renders a template, a tinker session) makes the next request that
 * recompiles it throw "touch(): Utime failed: Operation not permitted" and
 * the visitor gets a 500. That took sayzio.app down for 14 seconds on
 * 2026-09-22 at 12:21:07.
 *
 * deploy.sh avoids creating those files, but that is a rule someone has to
 * remember. This makes the failure harmless instead:
 *
 *   - The compiled file's CONTENT is already correct at the point touch()
 *     runs (that is why Laravel is touching rather than rewriting it), so
 *     the page can render as-is.
 *
 *   - It is then re-written in place via replace(), which writes a temp
 *     file and renames it over the old one. A rename only needs write
 *     access to the DIRECTORY, not ownership of the file, so the new copy
 *     belongs to the web server and the problem does not recur for that
 *     view.
 *
 * Every other exception is re-thrown untouched.
 */
class OwnershipTolerantBladeCompiler extends BladeCompiler
{
    public function compile($path = null)
    {
        try {
            parent::compile($path);
        } catch (\ErrorException $e) {
            if (! str_contains($e->getMessage(), 'touch()')) {
                throw $e;
            }

            $this->takeOwnership();
        }
    }

    /**
     * Replace the compiled file with an identical copy owned by this process.
     *
     * Best effort: if even that fails, the existing compiled file is still
     * correct and still renders -- the only cost is that it recompiles on
     * the next request too.
     */
    private function takeOwnership(): void
    {
        $compiledPath = $this->getCompiledPath($this->getPath());

        try {
            $this->files->replace($compiledPath, $this->files->get($compiledPath));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
