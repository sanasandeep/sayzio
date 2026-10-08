<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PreviewBiolinkBlock;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Actual Blade rendering, rather than checking whether a dispatch key exists. */
class BiolinkAllTypesRenderingTest extends TestCase
{
    use RefreshDatabase;

    public static function blockCases(): iterable
    {
        $types = array_unique(array_merge(array_keys(BiolinkBlock::TYPES), array_keys(BlockTypeRegistry::newTypes())));
        foreach ($types as $type) {
            foreach (['seeded', 'empty'] as $content) {
                foreach (['root', 'nested'] as $placement) {
                    yield "{$type}/{$content}/{$placement}" => [$type, $content, $placement];
                }
            }
        }
    }

    #[DataProvider('blockCases')]
    public function test_registered_blocks_compile_and_render(string $type, string $content, string $placement): void
    {
        $link = new Link;
        $link->forceFill(['id' => 0, 'alias' => 'preview', 'type' => 'biolink', 'settings' => ['biolink' => []]]);
        $settings = $content === 'seeded'
            ? BlockDefaults::withoutAdminOverrides(fn () => BlockDefaults::seededSettings($type))
            : [];
        $block = new PreviewBiolinkBlock;
        $block->forceFill(['id' => 1, 'link_id' => 0, 'type' => $type, 'settings' => $settings, 'is_active' => true]);
        $block->setRelation('link', $link);
        if ($placement === 'nested') {
            $parent = new PreviewBiolinkBlock;
            $parent->forceFill(['id' => 2, 'link_id' => 0, 'type' => 'card', 'settings' => ['gap' => 0], 'is_active' => true]);
            $parent->previewActiveChildren = collect([$block]);
            $parent->setRelation('link', $link);
            $block = $parent;
        }
        $html = view('admin.block-defaults.preview-frame', compact('type', 'block', 'link'))->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('cannot be previewed here', $html);
        $this->assertStringNotContainsString('{{ $', $html, 'Uncompiled PHP interpolation leaked to the visitor');
        $this->assertStringNotContainsString('Unknown block', $html);
        if ($placement === 'nested') {
            $this->assertStringContainsString('card-container-render', $html);
            $this->assertStringContainsString('data-block-id="1"', $html);
            $this->assertStringContainsString('data-block-type="' . $type . '"', $html);
        }
    }

    public function test_tip_provider_handles_and_tiktok_follow_urls_are_interpolated(): void
    {
        foreach (['buy_me_coffee', 'patreon', 'ko_fi', 'tiktok_profile'] as $type) {
            $block = new PreviewBiolinkBlock;
            $block->forceFill(['id' => 1, 'type' => $type, 'settings' => ['username' => 'studioatlas']]);
            $html = view('common.partials.biolink-block-render', ['link' => new Link, 'block' => $block, 's' => $block->settings, 'fontColor' => '#fff'])->render();
            $this->assertStringContainsString('@studioatlas', $html);
            $this->assertStringNotContainsString('{{ $', $html);
            if ($type === 'tiktok_profile') {
                $this->assertStringContainsString('https://tiktok.com/@studioatlas', $html);
            }
        }
    }
    public function test_vcard_downloads_use_separate_handlers_and_safe_javascript_strings(): void
    {
        foreach ([41, 42] as $id) {
            $block = new PreviewBiolinkBlock;
            $block->forceFill(['id' => $id, 'type' => 'vcard', 'settings' => ['name' => "Alex O'Neil", 'company' => 'Studio "Atlas"']]);
            $html = view('common.partials.biolink-block-render', ['link' => new Link, 'block' => $block, 's' => $block->settings, 'fontColor' => '#fff'])->render();
            $this->assertStringContainsString("function downloadVCard{$id}()", $html);
            $this->assertStringContainsString("onclick=\"downloadVCard{$id}()\"", $html);
            $this->assertStringContainsString('var data = JSON.parse(', $html);
            $this->assertStringNotContainsString('function downloadVCard()', $html);
        }
    }

    public function test_form_labels_are_serialized_as_javascript_instead_of_interpolated_into_quotes(): void
    {
        foreach (['contact_form', 'email_collector', 'whatsapp_number_subscribe', 'tip_jar'] as $type) {
            $block = new PreviewBiolinkBlock;
            $block->forceFill(['id' => 1, 'type' => $type, 'settings' => ['button_text' => "Let's connect"]]);
            $link = new Link;
            $link->forceFill(['id' => 0, 'alias' => 'preview', 'type' => 'biolink', 'settings' => ['biolink' => []]]);
            $html = view('admin.block-defaults.preview-frame', compact('type', 'block', 'link'))->render();
            $this->assertStringContainsString('Let', $html);
            $this->assertStringNotContainsString("&#039;Let&#039;s", $html);
            $this->assertStringContainsString('\\u0027', $html);
        }
    }

}
