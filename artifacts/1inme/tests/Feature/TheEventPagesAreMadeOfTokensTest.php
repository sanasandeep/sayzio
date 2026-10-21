<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nothing on an event page may wear a theme's colour as a literal.
 *
 * NoFieldIsInvisibleInLightModeTest scans form controls, app-wide.
 * That is the right scope for it, and it is also why two progress
 * tracks survived three sweeps: a bar is a div, and a div is not a
 * form control. Both the check-in page and the RSVP page drew their
 * track with rgba(255,255,255,0.08) -- the dark theme's value --
 * which on a white card is a white bar on white.
 *
 * So this one checks EVERY element, and is scoped to the six event
 * views because those are the ones actually clean. Widening the same
 * check app-wide finds roughly 199 across 59 views: a real backlog,
 * and not something to fail the suite on until it is worked through.
 *
 * Comments are stripped first. Several of these files explain the bug
 * they used to have, and quoting the literal in prose is not wearing
 * it.
 */
class TheEventPagesAreMadeOfTokensTest extends TestCase
{
    public static function views(): array
    {
        return [
            'event header'  => ['user/partials/event-header'],
            'People'        => ['user/links/event-people'],
            'Check-in'      => ['user/links/event-checkin'],
            'Ticketing'     => ['user/links/event-tickets'],
            'RSVPs'         => ['user/links/rsvps'],
            'Messages'      => ['user/links/broadcast'],
        ];
    }

    private function sourceWithoutComments(string $view): string
    {
        $source = file_get_contents(resource_path("views/{$view}.blade.php"));

        $this->assertNotFalse($source, "could not read {$view}");

        // {{-- blade --}} and /* css */, so a comment describing the
        // old literal does not read as the old literal.
        $source = preg_replace('~\{\{--.*?--\}\}~s', '', $source);

        return preg_replace('~/\*.*?\*/~s', '', $source);
    }

    #[DataProvider('views')]
    public function test_no_element_hardcodes_a_theme_colour(string $view): void
    {
        $source = $this->sourceWithoutComments($view);

        preg_match_all('~rgba\(\s*255\s*,\s*255\s*,\s*255\s*,[^)]*\)~', $source, $m);

        $this->assertSame([], $m[0], sprintf(
            "%s still hardcodes white %d time(s): %s\nIn light mode these render white on white.",
            $view,
            count($m[0]),
            implode(', ', array_unique($m[0]))
        ));
    }

    #[DataProvider('views')]
    public function test_the_view_still_compiles(string $view): void
    {
        // A premium pass that breaks a template is not a premium pass.
        // Rendering is covered by EveryEventTabWearsTheSameHeaderTest;
        // this is the cheap structural half -- balanced directives.
        $source = file_get_contents(resource_path("views/{$view}.blade.php"));

        foreach ([['@if', '@endif'], ['@foreach', '@endforeach'], ['@php', '@endphp'], ['@once', '@endonce']] as [$open, $close]) {
            $o = preg_match_all('~' . preg_quote($open, '~') . '\b~', $source);
            $c = preg_match_all('~' . preg_quote($close, '~') . '\b~', $source);

            $this->assertSame($c, $o, "{$view}: {$open} / {$close} are unbalanced ({$o} vs {$c})");
        }

        // The trap that broke the create pages and nearly the editor.
        $this->assertSame(0, preg_match_all('~@php\s*\(~', $source),
            "{$view}: a one-line @php(...) will pair with the next closing tag below it and swallow every directive in between");
    }

    public function test_the_progress_tracks_use_the_shared_meter(): void
    {
        // The two that were invisible. Named explicitly so a future
        // rewrite cannot quietly reintroduce an inline track.
        foreach (['user/links/event-checkin', 'user/links/rsvps'] as $view) {
            $this->assertStringContainsString('ev-meter', $this->sourceWithoutComments($view),
                "{$view} should draw its progress bar with the shared meter");
        }
    }
}
