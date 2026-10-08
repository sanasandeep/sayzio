<?php

namespace Tests\Unit;

use App\Modules\User\Support\BlockEditorFields;
use PHPUnit\Framework\TestCase;

class BlockEditorFieldsTest extends TestCase
{
    public function test_empty_nested_lists_keep_their_row_controls_without_exposing_internal_settings(): void
    {
        $defaults = ['title' => 'Menu', '_style' => ['bg_color' => '#fff'], 'sections' => [
            ['name' => 'Mains', 'items' => [['name' => 'Pasta', 'price' => '$12', 'available' => true]]],
        ]];
        $schema = BlockEditorFields::schema($defaults, ['sections' => []]);
        $this->assertArrayNotHasKey('_style', $schema);
        $this->assertSame('list', $schema['sections']['kind']);
        $this->assertSame('boolean', $schema['sections']['fields']['items']['fields']['available']['kind']);
        $this->assertSame([], BlockEditorFields::values($schema, ['sections' => []], $defaults)['sections']);
        $this->assertSame(['name' => '', 'items' => []], BlockEditorFields::emptyObject($schema['sections']['fields']));
    }

    public function test_saved_row_fields_and_false_checkbox_values_are_preserved(): void
    {
        $defaults = ['slots' => [['start' => '', 'taken' => false]]];
        $saved = ['slots' => [['start' => '2026-10-12 10:00', 'taken' => '0', 'duration' => '30 min'], ['start' => '', 'taken' => '1']]];
        $schema = BlockEditorFields::schema($defaults, $saved);
        $values = BlockEditorFields::values($schema, $saved);
        $this->assertSame(false, $values['slots'][0]['taken']);
        $this->assertSame(true, $values['slots'][1]['taken']);
        $this->assertSame('30 min', $values['slots'][0]['duration']);
    }

    public function test_clear_list_submissions_normalize_to_arrays_at_each_depth(): void
    {
        $defaults = ['sections' => [['name' => '', 'items' => [['name' => '']]]], 'tracks' => [['url' => '']]];
        $result = BlockEditorFields::normalizeEmptyLists(['sections' => [['name' => 'Mains', 'items' => '']], 'tracks' => ''], $defaults);
        $this->assertSame([], $result['sections'][0]['items']);
        $this->assertSame([], $result['tracks']);
    }
    public function test_color_inputs_are_validated_and_clearing_them_restores_defaults(): void
    {
        $result = BlockEditorFields::sanitizeColors([
            'accent_color' => 'rgb(12, 34, 56)', 'text_color' => '', 'color' => 'red;position:fixed',
            'items' => [['color' => '#123', 'name' => 'Studio']],
        ]);
        $this->assertSame('rgb(12, 34, 56)', $result['accent_color']);
        $this->assertArrayNotHasKey('text_color', $result);
        $this->assertArrayNotHasKey('color', $result);
        $this->assertSame('#123', $result['items'][0]['color']);
    }

}
