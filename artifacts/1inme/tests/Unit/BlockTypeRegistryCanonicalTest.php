<?php

namespace Tests\Unit;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockTypeRegistry;
use PHPUnit\Framework\TestCase;

class BlockTypeRegistryCanonicalTest extends TestCase
{
    public function test_every_registered_type_resolves_to_a_listed_canonical(): void
    {
        $slugs = BlockTypeRegistry::canonicalTypeSlugs();
        $this->assertSame(array_values(array_unique($slugs)), $slugs);
        foreach (array_merge(array_keys(BiolinkBlock::TYPES), array_keys(BlockTypeRegistry::newTypes())) as $type) {
            $this->assertContains(BlockTypeRegistry::canonical($type), $slugs, $type);
        }
        $this->assertContains('profile_card', $slugs);
        $this->assertNotContains('profile_card_v1', $slugs);
    }
}
