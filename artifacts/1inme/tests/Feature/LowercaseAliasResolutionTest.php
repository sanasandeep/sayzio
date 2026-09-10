<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A link whose alias was saved with capitals must answer at the ALL-LOWERCASE
 * spelling of that alias.
 *
 * This is the one casing that used to 404, and it is the one people type.
 * `Link::resolveByAlias()` tries an exact `where('alias', $alias)` first and
 * falls back to `WHERE LOWER(alias) = ?` when that misses -- but the fallback
 * was additionally gated on `$alias !== $lower`, i.e. "the request still has
 * some uppercase left in it". LOWER() is applied to the stored COLUMN, not to
 * the incoming string, so that guard excluded precisely the case the fallback
 * exists for:
 *
 *     stored "UMTbJth"   visitor types "UMTbJth"  -> exact hit          200
 *     stored "UMTbJth"   visitor types "UMTBJTH"  -> guard passes, LOWER() 200
 *     stored "UMTbJth"   visitor types "uMTbJth"  -> guard passes, LOWER() 200
 *     stored "UMTbJth"   visitor types "umtbjth"  -> guard SKIPS         404
 *
 * Confirmed against production before the fix: every casing of a real live
 * alias resolved except the all-lowercase one. Aliases keep whatever casing
 * their owner typed -- uniqueness is checked with LOWER(), but the raw value
 * is what gets stored -- so this was reachable for every mixed-case alias on
 * the platform, on the public page and on each API sub-action that resolves
 * independently.
 *
 * MixedCaseAliasSubActionsTest covers the sub-actions. This file pins the
 * primary surface and, more importantly, pins the all-lowercase DIRECTION,
 * which is the axis the old guard got backwards. A future "optimisation" that
 * skips the fallback when the request is already lowercase fails here.
 */
class LowercaseAliasResolutionTest extends TestCase
{
    use RefreshDatabase;

    private const STORED = 'SanaSandeep';

    private function biolink(string $alias = self::STORED): Link
    {
        return Link::create([
            'user_id'   => User::factory()->create()->id,
            'type'      => 'biolink',
            'alias'     => $alias,
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    /**
     * Every casing a visitor might type reaches the same row. The
     * all-lowercase entry is the regression; the others are here so a fix
     * that trades one casing for another cannot pass.
     *
     * @return array<string,array{0:string}>
     */
    public static function casings(): array
    {
        return [
            'exactly as stored' => [self::STORED],
            'all lowercase'     => ['sanasandeep'],
            'all uppercase'     => ['SANASANDEEP'],
            'different mixture' => ['sAnAsAnDeEp'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('casings')]
    public function test_the_public_page_answers_at_any_casing(string $typed): void
    {
        $link = $this->biolink();

        $this->assertNotNull(
            Link::resolveByAlias($typed, 'sayzio.app'),
            "Alias stored as '" . self::STORED . "' did not resolve when typed as '{$typed}'.",
        );

        $this->get('/' . $typed)->assertOk();

        // And it is the SAME row, not merely some row.
        $this->assertSame($link->id, Link::resolveByAlias($typed, 'sayzio.app')->id);
    }

    /**
     * Additional aliases (link_aliases rows) resolve through a second,
     * separate exact-then-LOWER() pair further down the same method, which
     * carried the same reversed guard.
     */
    public function test_an_additional_alias_answers_at_its_lowercase_spelling(): void
    {
        $link = $this->biolink('PrimaryOne');

        LinkAlias::create([
            'link_id' => $link->id,
            'alias'   => 'MyShop',
        ]);

        $resolved = Link::resolveByAlias('myshop', 'sayzio.app');

        $this->assertNotNull($resolved, "Additional alias 'MyShop' did not resolve as 'myshop'.");
        $this->assertSame($link->id, $resolved->id);
    }

    /**
     * The fallback widens which casings match; it must not widen WHICH ALIAS
     * matches. A different alias that merely shares a lowercase prefix, or
     * one that does not exist at all, still misses.
     */
    public function test_the_fallback_does_not_match_a_different_alias(): void
    {
        $this->biolink('SanaSandeep');

        $this->assertNull(Link::resolveByAlias('sanasandee', 'sayzio.app'));
        $this->assertNull(Link::resolveByAlias('sanasandeeps', 'sayzio.app'));
        $this->assertNull(Link::resolveByAlias('someoneelse', 'sayzio.app'));
    }

    /**
     * A disabled link stays disabled at every casing -- the fix widens which
     * spellings REACH a row, and must not change what happens once one is
     * reached.
     *
     * The app answers a disabled page with 410 Gone rather than 404, and that
     * distinction is the assertion worth making here: 410 is only reachable
     * *after* the alias resolved, so it pins both halves at once -- lowercase
     * now finds the row (it would have 404'd before the fix), and finding it
     * still does not serve it.
     */
    public function test_a_disabled_link_stays_disabled_at_lowercase(): void
    {
        $link = $this->biolink();
        $link->forceFill(['is_active' => false])->save();

        $this->get('/sanasandeep')->assertStatus(410);
        // Same answer as the exactly-cased spelling -- no casing is privileged.
        $this->get('/' . self::STORED)->assertStatus(410);
    }
}
