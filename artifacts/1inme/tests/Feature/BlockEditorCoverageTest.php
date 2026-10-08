<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockEditorFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockEditorCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_block_default_has_an_editable_public_field_schema(): void
    {
        foreach (array_keys(BiolinkBlock::TYPES) as $type) {
            $defaults = BlockEditorFields::defaultsForType($type);
            $schema = BlockEditorFields::schema($defaults);
            foreach ($defaults as $key => $value) {
                if (preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
                    $this->assertArrayHasKey($key, $schema, $type.'.'.$key);
                }
            }
        }
    }

    public function test_structured_form_compiles_nested_item_names_and_controls(): void
    {
        $defaults = BlockDefaults::contentForType('menu');
        $html = view('user.links.partials.block-structured-fields', [
            'fields' => BlockEditorFields::schema($defaults), 'access' => 'content',
            'nameExpression' => "'settings'", 'depth' => 0,
            'inputClass' => 'input', 'labelClass' => 'label',
        ])->render();
        $this->assertStringContainsString('data-editor-field="accent_color"', $html);
        $this->assertStringContainsString('data-editor-field="sections"', $html);
        $this->assertStringContainsString('data-editor-field="items"', $html);
        $this->assertStringContainsString('rowIndex1', $html);
        $this->assertStringContainsString('Remove item', $html);
        $this->assertStringNotContainsString('data-editor-field="_placeholder"', $html);
    }
    public function test_remaining_fixed_surfaces_and_featured_link_text_use_block_colors(): void
    {
        foreach (['rsvp', 'alert', 'notification', 'link_big'] as $type) {
            $block = new BiolinkBlock(['type' => $type, 'settings' => [
                'text' => 'Studio', 'type' => 'info', '_style' => ['bg_color' => '#123456', 'text_color' => '#abcdef'],
            ]]);
            $block->id = 123;
            $html = view('common.partials.biolink-block-render', [
                'link' => new \App\Modules\User\Models\Link,
                'block' => $block, 's' => $block->settings,
                'fontColor' => '#ffffff', 'globalTheme' => [],
            ])->render();
            $this->assertStringContainsString('#abcdef', $html, $type);
            $this->assertStringNotContainsString('background:#fff; color:#111;', $html, $type);
        }
    }

    public function test_partial_edits_preserve_content_and_explicitly_empty_lists_clear_it(): void
    {
        $user = \App\Modules\User\Models\User::factory()->create();
        $link = \App\Modules\User\Models\Link::create([
            'user_id' => $user->id, 'type' => 'biolink', 'alias' => \App\Modules\User\Models\Link::generateAlias(),
            'title' => 'Studio', 'is_active' => true,
        ]);
        $block = BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'file_list', 'sort_order' => 0, 'is_active' => true,
            'settings' => ['title' => 'Files', 'items' => [['name' => 'Guide', 'url' => 'https://example.com/guide.pdf']]],
        ]);
        $this->actingAs($user)->putJson(route('user.links.blocks.update', [$link, $block]), [
            'settings' => ['accent_color' => '#123456'],
        ])->assertOk();
        $this->assertSame('Guide', $block->fresh()->settings['items'][0]['name']);
        $this->assertSame('Files', $block->fresh()->settings['title']);
        $this->putJson(route('user.links.blocks.update', [$link, $block]), [
            'settings' => ['items' => ''],
        ])->assertOk();
        $this->assertSame([], $block->fresh()->settings['items']);
    }

}
