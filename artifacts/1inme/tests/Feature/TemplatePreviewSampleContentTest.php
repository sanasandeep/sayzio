<?php

namespace Tests\Feature;

use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Tests\TestCase;

class TemplatePreviewSampleContentTest extends TestCase
{
    public function test_admin_media_is_reflected_without_accepting_unsafe_schemes(): void
    {
        $builder = new TemplatePreviewLayoutBuilder;
        $this->assertSame('https://example.com/studio.jpg', $builder->build([
            ['type' => 'image', 'settings' => ['url' => 'https://example.com/studio.jpg']],
        ])[0][0]['img']);
        $this->assertSame('https://example.com/alex.jpg', $builder->build([
            ['type' => 'profile_card_v1', 'settings' => ['avatar' => 'https://example.com/alex.jpg']],
        ])[0][0]['img']);
        $this->assertStringContainsString('/images/auth-slider/', $builder->build([
            ['type' => 'image', 'settings' => ['url' => 'javascript:alert(1)']],
        ])[0][0]['img']);
    }

    public function test_media_uses_bundled_photography_and_forms_have_compact_proportions(): void
    {
        $builder = new TemplatePreviewLayoutBuilder;
        foreach (['image', 'image_grid', 'image_slider', 'video', 'audio', 'profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4'] as $type) {
            $cell = $builder->build([['type' => $type]])[0][0];
            $this->assertStringContainsString('/images/auth-slider/photo-', $cell['img']);
            $this->assertFileExists(public_path(parse_url($cell['img'], PHP_URL_PATH)));
        }
        $this->assertSame(62, $builder->build([['type' => 'email_subscribe']])[0][0]['h']);
        $this->assertNotEmpty($builder->cellFor('badge')['text']);
    }

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
