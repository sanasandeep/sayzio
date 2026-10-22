<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Create Link step indicator rendered as the literal text
 * "1Choose2Set up".
 *
 * Not a cascade collision and not a theme hole, which is what made it
 * worth writing down: .create-progress, .step-dot and .progress-line
 * were used in the markup and defined NOWHERE in the codebase. The
 * spans laid out inline with no dot, no gap and a zero-width rule, so
 * each number ran into its label. Nothing overrode anything; the rules
 * simply did not exist.
 *
 * A unit test cannot see a step indicator. What it can do is refuse to
 * let the markup and its stylesheet drift apart again, which is the
 * only part of this that is mechanically checkable.
 */
class TheCreateStepsLookLikeStepsTest extends TestCase
{
    /**
     * NOT view(). Illuminate\Foundation\Testing\TestCase already declares
     * `protected function view()`, and a private override narrows the
     * visibility, which PHP rejects at CLASS LOAD time with a fatal. That
     * killed the whole suite at bootstrap, not just this file, and php -l
     * cannot see it: the file is syntactically perfect and the clash only
     * exists against the parent class.
     */
    private function source(): string
    {
        return (string) file_get_contents(
            resource_path('views/user/links/create.blade.php')
        );
    }

    public function test_every_class_the_step_indicator_uses_is_actually_styled(): void
    {
        $source = $this->source();

        // The control first: if the markup ever moves or is renamed,
        // the rest of this test would pass by checking nothing.
        $this->assertStringContainsString('class="create-progress"', $source,
            'the step indicator markup is gone from this view, so this test no longer guards it');

        foreach (['create-progress', 'step-dot', 'progress-line'] as $class) {
            $this->assertMatchesRegularExpression(
                '/\.' . preg_quote($class, '/') . '\s*[\{,>:\s]/',
                $source,
                ".{$class} is used in the step indicator but has no CSS rule in this view. "
                . 'That is exactly how it last shipped reading "1Choose2Set up".'
            );
        }
    }

    public function test_the_steps_are_laid_out_rather_than_left_inline(): void
    {
        // The specific failure was inline layout: no flex, so no gap
        // between a number and its word. Pin the property that fixes
        // it rather than the whole rule, which is free to change.
        $this->assertMatchesRegularExpression(
            '/\.create-progress\s*\{[^}]*display:\s*inline-flex/',
            $this->source(),
            'the step indicator is not laid out, so its numbers will run into its labels again'
        );
    }

    public function test_the_indicator_introduces_no_colour_literal_except_on_the_accent(): void
    {
        // Same rule the rest of the app follows: a literal is allowed
        // when the ground it sits on is the accent, because then it is
        // correct in every theme. Anywhere else it is a light-mode hole.
        preg_match_all(
            '/\.create-progress[^{]*\{([^}]*)\}/',
            $this->source(),
            $rules
        );

        $this->assertNotEmpty($rules[1], 'no .create-progress rules found, so this checked nothing');

        foreach ($rules[1] as $body) {
            if (str_contains($body, 'var(--accent)')) {
                continue;   // white on an accent fill is the allowed case
            }

            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $body),
                "a colour literal off the accent: {$body}");
        }
    }
}
