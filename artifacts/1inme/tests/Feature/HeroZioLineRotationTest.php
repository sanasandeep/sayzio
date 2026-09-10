<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\ZioLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zio's speech bubbles in the hero.
 *
 * The lines have been admin-editable for a while, but the turn-taking was
 * written for exactly four of them: a 16-second loop, each bubble offset by
 * four seconds, keyframes showing each one for a quarter of the loop.
 *
 * So they were editable only as long as an admin kept the count at four. A
 * fifth line takes a 20s delay against a 16s loop, which puts it on the same
 * offset as the second -- two bubbles stacked in one slot. Three lines leave
 * a four-second hole with Zio gesturing at nothing.
 *
 * These assertions are on the numbers the page emits rather than on how it
 * looks, because the failure is arithmetic: one slot is four seconds, and the
 * cycle is however many slots there are.
 */
class HeroZioLineRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ZioLine::query()->delete();
        ZioLine::flushCache();
    }

    protected function tearDown(): void
    {
        ZioLine::flushCache();
        parent::tearDown();
    }

    private function seedLines(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            ZioLine::create(['text' => "Line number {$i}", 'is_active' => true, 'sort_order' => $i]);
        }
        ZioLine::flushCache();
    }

    /**
     * The shipped fallback is four lines, and four lines is what the original
     * hand-written keyframes were tuned for. The derivation has to reproduce
     * them exactly, or this change is a redesign wearing a bug fix's clothes.
     */
    public function test_four_lines_reproduce_the_original_timing(): void
    {
        $this->seedLines(4);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('--zio-cycle: 16s', $html);
        $this->assertStringContainsString('2.5%, 21%', $html);
        $this->assertStringContainsString('24%, 100%', $html);
    }

    public function test_the_cycle_is_four_seconds_per_line(): void
    {
        foreach ([1, 3, 5, 7] as $n) {
            $this->seedLines($n);

            $this->get('/')
                ->assertOk()
                ->assertSee('--zio-cycle: ' . ($n * 4) . 's', false);

            ZioLine::query()->delete();
            ZioLine::flushCache();
        }
    }

    /**
     * Each bubble is visible for its own slot and no longer. If the "hold"
     * percentage ever exceeded one slot's share of the cycle, two bubbles
     * would be on screen at once -- which is the exact failure a fifth line
     * used to cause.
     */
    public function test_no_two_bubbles_can_be_visible_at_once(): void
    {
        foreach ([2, 5, 9] as $n) {
            $this->seedLines($n);

            $html = $this->get('/')->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '/@keyframes zioSay \{.*?([\d.]+)%,\s*([\d.]+)%\s*\{ opacity: 1/s',
                $html,
                "No visible-window keyframe found for {$n} lines."
            );
            preg_match('/@keyframes zioSay \{.*?([\d.]+)%,\s*([\d.]+)%\s*\{ opacity: 1/s', $html, $m);

            $slotShare = 100 / $n;
            $this->assertLessThanOrEqual(
                $slotShare,
                (float) $m[2],
                "With {$n} lines a bubble holds past its own slot ({$m[2]}% of a {$slotShare}% slot)."
            );

            ZioLine::query()->delete();
            ZioLine::flushCache();
        }
    }

    public function test_a_line_added_in_admin_reaches_the_hero(): void
    {
        $this->seedLines(3);
        ZioLine::create(['text' => 'A brand new thing to say', 'is_active' => true, 'sort_order' => 4]);
        ZioLine::flushCache();

        $this->get('/')
            ->assertOk()
            ->assertSee('A brand new thing to say')
            ->assertSee('--zio-cycle: 16s', false);
    }

    /**
     * Hiding every line must not leave Zio mid-gesture with a blank bubble,
     * so the shipped set comes back -- and the cycle has to follow it rather
     * than being computed from a count of zero.
     */
    public function test_hiding_every_line_falls_back_without_breaking_the_cycle(): void
    {
        $this->seedLines(5);
        ZioLine::query()->update(['is_active' => false]);
        ZioLine::flushCache();

        $this->get('/')
            ->assertOk()
            ->assertSee("Hi, I'm Zio")
            ->assertSee('--zio-cycle: 16s', false);
    }
}
