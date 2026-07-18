<?php

namespace Tests\Unit\Support;

use App\Support\UniqueSuffix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UniqueSuffixTest extends TestCase
{
    private const TABLE = 'unique_suffix_test_rows';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists(self::TABLE);
        Schema::create(self::TABLE, function ($table) {
            $table->id();
            $table->string('slug');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        parent::tearDown();
    }

    private function insertSlugs(array $slugs): void
    {
        DB::table(self::TABLE)->insert(
            array_map(fn ($slug) => ['slug' => $slug], $slugs)
        );
    }

    private function resolve(string $base): string
    {
        return UniqueSuffix::resolve(DB::table(self::TABLE), $base);
    }

    public function test_returns_base_when_no_collision(): void
    {
        $this->insertSlugs(['other', 'other-2']);

        $this->assertSame('my-board', $this->resolve('my-board'));
    }

    public function test_base_taken_returns_base_2(): void
    {
        $this->insertSlugs(['my-board']);

        $this->assertSame('my-board-2', $this->resolve('my-board'));
    }

    public function test_gap_in_numbering_continues_from_highest(): void
    {
        $this->insertSlugs(['my-board', 'my-board-5']);

        $this->assertSame('my-board-6', $this->resolve('my-board'));
    }

    public function test_non_numeric_suffixes_do_not_affect_numbering(): void
    {
        $this->insertSlugs(['my-board', 'my-board-draft', 'my-board-2a']);

        $this->assertSame('my-board-2', $this->resolve('my-board'));
    }

    public function test_numbered_variants_taken_but_base_free_returns_base(): void
    {
        $this->insertSlugs(['my-board-2', 'my-board-3']);

        $this->assertSame('my-board', $this->resolve('my-board'));
    }

    public function test_like_wildcards_in_base_do_not_over_match(): void
    {
        // If % were not escaped, "a%b" would match "aXb-9" via LIKE and
        // resolve would jump to "a%b-10" despite "a%b" itself being free
        // only through the exact-match branch.
        $this->insertSlugs(['aXb-9', 'a_b-7']);

        $this->assertSame('a%b', $this->resolve('a%b'));
        $this->assertSame('a-b', $this->resolve('a-b'));

        // Now take the wildcard base itself: numbering must only consider
        // literal "a%b-N" rows, not "aXb-9".
        $this->insertSlugs(['a%b', 'a%b-3']);
        $this->assertSame('a%b-4', $this->resolve('a%b'));
    }

    public function test_works_with_custom_column(): void
    {
        Schema::dropIfExists('unique_suffix_alias_rows');
        Schema::create('unique_suffix_alias_rows', function ($table) {
            $table->id();
            $table->string('alias');
        });

        try {
            DB::table('unique_suffix_alias_rows')->insert([
                ['alias' => 'promo'],
                ['alias' => 'promo-2'],
            ]);

            $this->assertSame(
                'promo-3',
                UniqueSuffix::resolve(DB::table('unique_suffix_alias_rows'), 'promo', 'alias')
            );
        } finally {
            Schema::dropIfExists('unique_suffix_alias_rows');
        }
    }
}
