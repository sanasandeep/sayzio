<?php

namespace Tests\Unit\Support;

use App\Modules\User\Support\EventCategories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the legacy event-category normalization mapping (Task #3624's
 * {@see EventCategories::slugForLegacy()}) against silent regressions.
 *
 * slugForLegacy() maps old free-text `settings['event_category']` values onto
 * curated slugs by reusing LEGACY_KEYWORD_ICONS — the SAME keyword map that
 * drives icon() guessing. Because that map is shared, a future edit made for
 * icon reasons could quietly re-route how legacy values group in the /events
 * directory, with nothing else failing. These cases pin the intended mapping
 * for representative legacy inputs and the "leave untouched" (null) contract
 * for values that are already curated slugs, empty, "Other", or genuinely
 * custom.
 */
class EventCategoriesSlugForLegacyTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:?string}>
     */
    public static function legacyMappings(): array
    {
        return [
            // Representative legacy free-text values that must resolve to a
            // curated slug. Includes case-insensitivity and keyword-precedence
            // cases ("Tech Meetup" must win technology over community; "yoga
            // class" must win sports_fitness over education).
            'exact keyword, capitalized' => ['Music', 'music'],
            'phrase containing keyword'  => ['live music', 'music'],
            'tech beats meetup'          => ['Tech Meetup', 'technology'],
            'food ampersand drink'       => ['food & drink', 'food_drink'],
            'yoga beats class'           => ['yoga class', 'sports_fitness'],

            // Values that must be LEFT UNTOUCHED (null): already-curated slugs
            // (nothing to change), empty/whitespace, the "Other" sentinel, and
            // genuinely custom text that matches no keyword.
            'already a curated slug'     => ['music', null],
            'empty string'              => ['', null],
            'whitespace only'           => ['   ', null],
            'other sentinel'            => [EventCategories::OTHER, null],
            'unmatched custom text'     => ['Underwater Basket Weaving', null],
        ];
    }

    #[DataProvider('legacyMappings')]
    public function test_slug_for_legacy_maps_expected(string $input, ?string $expected): void
    {
        $this->assertSame($expected, EventCategories::slugForLegacy($input));
    }
}
