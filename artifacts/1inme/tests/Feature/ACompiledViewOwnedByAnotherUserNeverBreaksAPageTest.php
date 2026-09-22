<?php

namespace Tests\Feature;

use App\Support\View\OwnershipTolerantBladeCompiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Tests\TestCase;

/**
 * The 14 seconds sayzio.app spent returning 500s on 2026-09-22.
 *
 * Production runs deploys as `sayzio` and PHP-FPM as `apache`. A view
 * compiled by `sayzio` and later recompiled by `apache` made Laravel call
 * touch() on a file `apache` does not own, which throws "touch(): Utime
 * failed: Operation not permitted" and fails the request.
 *
 * These tests reproduce that for real, not with a mock: the compiled view
 * is created by this process (root in CI and the sandbox) and then
 * recompiled in a forked child that has dropped to the `nobody` user --
 * the same "compiled by one user, recompiled by another" split, down to the
 * kernel returning EPERM. The first test proves the scenario reproduces the
 * outage with Laravel's own compiler; the second proves ours survives it.
 */
class ACompiledViewOwnedByAnotherUserNeverBreaksAPageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork') || ! function_exists('posix_setuid') || posix_geteuid() !== 0) {
            $this->markTestSkipped('Needs root plus pcntl/posix to act as two different users.');
        }
        if (posix_getpwnam('nobody') === false) {
            $this->markTestSkipped('Needs a `nobody` user to recompile as.');
        }

        $this->dir = sys_get_temp_dir().'/blade-owner-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/views', 0777, true);
        mkdir($this->dir.'/compiled', 0777, true);
        // Like the ACL on the server: the web user can write the DIRECTORY,
        // it just does not own the files the deploy user put in it.
        chmod($this->dir, 0777);
        chmod($this->dir.'/views', 0777);
        chmod($this->dir.'/compiled', 0777);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            (new Filesystem())->deleteDirectory($this->dir);
        }
        parent::tearDown();
    }

    /**
     * Compile as this user, then make the template look newer so the next
     * compile takes the touch() path -- exactly the state a deploy leaves.
     */
    private function templateCompiledByTheDeployUser(): string
    {
        $template = $this->dir.'/views/page.blade.php';
        file_put_contents($template, '<p>{{ $name }}</p>');
        chmod($template, 0644);

        (new BladeCompiler(new Filesystem(), $this->dir.'/compiled'))->compile($template);

        foreach (glob($this->dir.'/compiled/*') as $compiled) {
            chmod($compiled, 0644);
            touch($compiled, time() - 60);
        }
        touch($template, time());

        return $template;
    }

    /**
     * Recompile as `nobody` in a child process, with warnings raised as
     * exceptions the way Laravel's HandleExceptions does in production.
     *
     * @return array{ok: bool, error: string, owner: string}
     */
    private function recompileAsAnotherUser(string $compilerClass, string $template): array
    {
        $result = $this->dir.'/result.json';
        $pid    = pcntl_fork();

        if ($pid === 0) {
            $nobody = posix_getpwnam('nobody');
            posix_setgid($nobody['gid']);
            posix_setuid($nobody['uid']);

            set_error_handler(function ($level, $message, $file = '', $line = 0) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            });

            $out = ['ok' => true, 'error' => '', 'owner' => ''];
            try {
                (new $compilerClass(new Filesystem(), $this->dir.'/compiled'))->compile($template);
            } catch (\Throwable $e) {
                $out = ['ok' => false, 'error' => $e->getMessage(), 'owner' => ''];
            }
            $compiled = glob($this->dir.'/compiled/*.php')[0] ?? null;
            if ($compiled) {
                clearstatcache();
                $out['owner'] = posix_getpwuid(fileowner($compiled))['name'] ?? '?';
            }
            file_put_contents($result, json_encode($out));

            // Leave without running PHPUnit's shutdown in the child.
            posix_kill(posix_getpid(), SIGKILL);
        }

        pcntl_waitpid($pid, $status);

        return json_decode((string) @file_get_contents($result), true)
            ?? ['ok' => false, 'error' => 'child produced no result', 'owner' => ''];
    }

    /** The scenario is real: Laravel's own compiler fails exactly as production did. */
    public function test_laravels_compiler_fails_the_way_production_did(): void
    {
        $template = $this->templateCompiledByTheDeployUser();

        $r = $this->recompileAsAnotherUser(BladeCompiler::class, $template);

        $this->assertFalse($r['ok'], 'if this passes, the test no longer reproduces the outage');
        $this->assertStringContainsString('touch()', $r['error']);
    }

    /** Ours renders the page instead, and takes the file over so it stays fixed. */
    public function test_our_compiler_survives_it_and_takes_the_file_over(): void
    {
        $template = $this->templateCompiledByTheDeployUser();

        $r = $this->recompileAsAnotherUser(OwnershipTolerantBladeCompiler::class, $template);

        $this->assertTrue($r['ok'], 'the page must render, not 500: '.$r['error']);
        $this->assertSame('nobody', $r['owner'],
            'the compiled view must now belong to the web user, so the next request does not hit this at all');

        $compiled = glob($this->dir.'/compiled/*.php')[0];
        $this->assertStringContainsString('$name', (string) file_get_contents($compiled),
            'and it must still be the compiled template, not an empty file');
    }

    /** Any other compile failure is still loud. */
    public function test_other_compile_errors_are_not_swallowed(): void
    {
        $compiler = new OwnershipTolerantBladeCompiler(new Filesystem(), $this->dir.'/compiled');

        $this->expectException(\Throwable::class);
        $compiler->compile($this->dir.'/views/does-not-exist.blade.php');
    }

    /** And it is the compiler the app actually uses. */
    public function test_the_app_uses_it(): void
    {
        $this->assertInstanceOf(OwnershipTolerantBladeCompiler::class, app('blade.compiler'));
        $this->assertSame('<p>ok</p>', trim(\Illuminate\Support\Facades\Blade::render('<p>{{ $v }}</p>', ['v' => 'ok'])),
            'and Blade still renders through it');
    }
}
