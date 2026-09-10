<?php

namespace Tests\Support;

/**
 * Pattern assertions that do not put the haystack in the failure message.
 *
 * `assertMatchesRegularExpression($pattern, $html)` against a rendered page or
 * a large Blade file does not report a failure -- it hangs. PHPUnit puts the
 * full subject into the message and runs it through its exporter, and the
 * homepage is 700KB while home.blade.php is a quarter of a megabyte. A guard
 * that hangs is worse than no guard: locally it looks like an infinite loop
 * and in CI it is a timeout with no finding attached.
 *
 * This was found twice in one batch -- once writing a test, once running an
 * earlier test against older code -- so it is a helper rather than a habit.
 *
 * Every method here matches first and asserts on the RESULT, so the message
 * carries only what the caller wrote plus a short excerpt where one helps.
 */
trait AssertsAgainstLargeSubjects
{
    protected function assertPatternFound(string $pattern, string $subject, string $message = ''): void
    {
        $this->assertTrue(
            (bool) preg_match($pattern, $subject),
            $message !== '' ? $message : "no match for {$pattern}"
        );
    }

    protected function assertPatternAbsent(string $pattern, string $subject, string $message = ''): void
    {
        if (! preg_match($pattern, $subject, $m, PREG_OFFSET_CAPTURE)) {
            $this->assertTrue(true);

            return;
        }

        // A 120-character window around the hit, which is what a reader needs
        // to find it, and nothing like the whole subject.
        $at = (int) $m[0][1];
        $excerpt = trim(preg_replace('/\s+/', ' ', substr($subject, max(0, $at - 40), 120)));

        $this->fail(($message !== '' ? $message : "unexpected match for {$pattern}") . "\n\n  found at offset {$at}: …{$excerpt}…");
    }

    protected function assertSubjectContains(string $needle, string $subject, string $message = ''): void
    {
        $this->assertTrue(
            str_contains($subject, $needle),
            $message !== '' ? $message : "\"{$needle}\" is not in the subject"
        );
    }

    protected function assertSubjectDoesNotContain(string $needle, string $subject, string $message = ''): void
    {
        $this->assertFalse(
            str_contains($subject, $needle),
            $message !== '' ? $message : "\"{$needle}\" is in the subject and should not be"
        );
    }
}
