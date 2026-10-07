<?php

namespace Tests\Feature;

use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Tests\TestCase;

class TemplatePreviewSampleContentTest extends TestCase
{
    public function test_preview_uses_safe_template_copy_and_readable_demo_fallbacks(): void
    {
        $items = [
            ['type' => 'heading', 'settings' => ['text' => '<b>Made for makers</b>']],
            ['type' => 'profile_card_v1', 'settings' => ['name' => 'Your Name', 'title' => 'What you do']],
            ['type' => 'link', 'settings' => ['text' => 'Explore the collection']],
            ['type' => 'paragraph', 'settings' => ['text' => '<script></script>']],
        ];
        $before = $items;
        $rows = (new TemplatePreviewLayoutBuilder)->build($items);
        $this->assertSame('Made for makers', $rows[0][0]['text']);
        $this->assertSame('Alex Morgan', $rows[1][0]['text']);
        $this->assertSame('Designer & storyteller', $rows[1][0]['sub_text']);
        $this->assertSame('Explore the collection', $rows[2][0]['text']);
        $this->assertNotEmpty($rows[3][0]['text']);
        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual(24, $row[0]['h']);
        }
        $this->assertSame($before, $items);
    }
}
