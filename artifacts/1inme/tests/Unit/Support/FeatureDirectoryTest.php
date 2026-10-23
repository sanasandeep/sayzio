<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\FeatureDirectory;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Support\LinkTypeCategories;
use PHPUnit\Framework\TestCase;

class FeatureDirectoryTest extends TestCase
{
    public function test_old_saved_categories_gain_missing_features_without_overwriting_edits(): void
    {
        $stored = [[
            'id' => 'analytics', 'heading' => 'My reporting', 'intro' => 'Custom introduction',
            'features' => [['name' => 'Visitor analytics', 'description' => 'Custom copy', 'link' => '/my-report']],
        ]];
        $categories = array_column(FeatureDirectory::categories($stored), null, 'id');
        $this->assertSame('My reporting', $categories['analytics']['heading']);
        $this->assertSame('Custom copy', $categories['analytics']['features'][0]['description']);
        $this->assertSame('/my-report', $categories['analytics']['features'][0]['link']);
        $names = array_column($categories['analytics']['features'], 'name');
        $this->assertContains('Audience Insights', $names);
        $this->assertCount(1, array_keys($names, 'Visitor analytics'));
    }

    public function test_every_picker_format_appears_and_no_category_is_lost_or_repeated(): void
    {
        $categories = FeatureDirectory::categories([]);
        $formats = array_column(array_column($categories, null, 'id')['link-types']['features'], 'name');
        foreach (LinkTypeCategories::categories() as $group) {
            foreach ($group['types'] as $type) $this->assertContains($type['label'], $formats);
        }
        $grouped = array_merge(...array_column(FeatureDirectory::groups($categories), 'items'));
        $ids = array_column($grouped, 'id');
        $this->assertEqualsCanonicalizing(array_column($categories, 'id'), $ids);
        $this->assertCount(count(array_unique($ids)), $ids);
        $this->assertCount(9, FeatureDirectory::groups($categories));
        foreach (SitePagesContent::featuresCategoriesDefault() as $category) $this->assertContains($category['id'], $ids);
    }

    public function test_custom_admin_categories_stay_visible_and_merge_is_idempotent(): void
    {
        $custom = ['id' => 'custom', 'heading' => 'Customer tool', 'features' => [['name' => 'Custom capability', 'description' => 'Still here']]];
        $categories = FeatureDirectory::categories([$custom]);
        $this->assertSame($categories, FeatureDirectory::categories($categories));
        $groups = FeatureDirectory::groups($categories);
        $this->assertSame('more', end($groups)['id']);
        $this->assertSame('Custom capability', end($groups)['items'][0]['features'][0]['name']);
    }
}
