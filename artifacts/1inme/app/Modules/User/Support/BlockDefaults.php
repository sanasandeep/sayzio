<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Per-type "first-paint" defaults: structural style tokens, placeholder
 * media URLs, and a friendly text/seed payload for new blocks.
 *
 * Style overrides intentionally only set structural tokens (radius,
 * padding, shadow_preset, display_mode, glass_preset, font sizing,
 * border style/width). Colour fields (bg_color, border_color,
 * text_color) are left to {@see BiolinkBlock::STYLE_DEFAULTS} so the
 * active biolink theme resolves them at render time.
 */
class BlockDefaults
{
    /**
     * Per-block-type structural style overrides layered on top of
     * {@see BiolinkBlock::STYLE_DEFAULTS}. Variant catalog payloads
     * still fully replace `_style` at apply-variant time.
     *
     * @return array<string,mixed>
     */
    public static function styleForType(string $type): array
    {
        $canonical = BlockTypeRegistry::canonical($type);

        return match ($canonical) {
            'heading', 'heading_logo' => [
                'font_size' => '28', 'font_weight' => '700',
                'display_mode' => 'content', 'padding' => '8',
            ],
            'paragraph', 'paragraph_rich', 'markdown' => [
                'font_size' => '16', 'display_mode' => 'content', 'padding' => '6',
            ],

            'link', 'link_big', 'cta_button', 'featured_pin' => [
                'border_radius' => '14', 'padding' => '14',
                'shadow_preset' => 'soft', 'display_mode' => 'card',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'divider', 'spacer' => [
                'display_mode' => 'content', 'padding' => '0',
            ],

            'card', 'card_slider', 'scroll_cards' => [
                'border_radius' => '20', 'padding' => '20',
                'glass_preset' => 'light', 'shadow_preset' => 'soft',
            ],

            'profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4' => [
                'border_radius' => '20', 'padding' => '20',
                'shadow_preset' => 'soft',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'image', 'image_grid', 'image_slider', 'image_slider_v2',
            'header_video', 'video', 'audio', 'pdf_document', 'powerpoint',
            'excel', 'file' => [
                'border_radius' => '16', 'shadow_preset' => 'soft',
                'display_mode' => 'card', 'padding' => '8',
            ],

            'faq', 'faq_v2', 'poll', 'quiz', 'testimonials', 'review',
            'timeline', 'timeline_staged', 'social_proof', 'ai_companion',
            'insider', 'fan_leaderboard', 'roadmap', 'product', 'service',
            'catalog', 'market', 'price', 'donation', 'coupon',
            'one_time_offer', 'paypal', 'buy_me_coffee', 'patreon', 'ko_fi',
            'email_collector', 'email_subscribe', 'phone_collector',
            'contact_form', 'whatsapp_widget', 'whatsapp_item',
            'whatsapp_channel_subscribe', 'whatsapp_number_subscribe',
            'list_pricing', 'menu', 'menu_section', 'event_list',
            'testimonial_carousel', 'stats', 'affiliate_links',
            'booking_slots', 'file_list', 'audio_list', 'link_tree_group',
            'tabs', 'accordion' => [
                'border_radius' => '16', 'padding' => '16',
                'shadow_preset' => 'soft', 'display_mode' => 'card',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'alert', 'badge', 'notification' => [
                'border_radius' => '999', 'padding' => '10',
                'display_mode' => 'card', 'shadow_preset' => 'soft',
            ],

            default => [
                'border_radius' => '14', 'padding' => '12',
                'display_mode' => 'card', 'shadow_preset' => 'soft',
            ],
        };
    }

    /**
     * Absolute URL to a placeholder image asset. Resolved through
     * Laravel's asset() helper so APP_URL is applied (the URL
     * sanitizer only accepts http(s) absolutes).
     */
    public static function placeholderUrl(string $kind): string
    {
        $map = [
            'image'        => 'image.svg',
            'image_square' => 'image-square.svg',
            'cover'        => 'cover.svg',
            'avatar'       => 'avatar.svg',
            'logo'         => 'logo.svg',
            'document'     => 'document.svg',
        ];
        $file = $map[$kind] ?? 'image.svg';
        return asset('block-placeholders/' . $file);
    }

    /**
     * Project-hosted sample media URLs for non-image media blocks.
     * Files live under public/block-placeholders/.
     */
    public static function sampleMediaUrl(string $kind): string
    {
        $file = match ($kind) {
            'video' => 'sample.mp4',
            'audio' => 'sample.mp3',
            'pdf'   => 'sample.pdf',
            'pptx'  => 'sample.pptx',
            'xlsx'  => 'sample.xlsx',
            default => '',
        };
        return $file === '' ? '' : asset('block-placeholders/' . $file);
    }
}
