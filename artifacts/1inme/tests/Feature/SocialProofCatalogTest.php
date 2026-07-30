<?php

namespace Tests\Feature;

use App\Modules\User\Models\SocialProof;
use Tests\TestCase;

class SocialProofCatalogTest extends TestCase
{
    public function test_catalog_has_exactly_fifty_templates(): void
    {
        $this->assertCount(50, SocialProof::TYPES);
    }

    public function test_type_groups_partition_the_full_catalog(): void
    {
        $grouped = [];
        foreach (SocialProof::TYPE_GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, SocialProof::TYPES, "Group '{$group}' references unknown type '{$key}'");
                $this->assertNotContains($key, $grouped, "Type '{$key}' appears in more than one group");
                $grouped[] = $key;
            }
        }
        $this->assertCount(50, $grouped);
        $this->assertEqualsCanonicalizing(array_keys(SocialProof::TYPES), $grouped);
    }

    public function test_every_type_has_description_icon_and_defaults(): void
    {
        foreach (array_keys(SocialProof::TYPES) as $type) {
            $this->assertArrayHasKey($type, SocialProof::TYPE_DESCRIPTIONS, "Missing description for '{$type}'");
            $this->assertNotSame('', trim(SocialProof::TYPE_DESCRIPTIONS[$type]), "Empty description for '{$type}'");
            $this->assertArrayHasKey($type, SocialProof::TYPE_ICONS, "Missing icon for '{$type}'");
            $this->assertStringStartsWith('fa-', SocialProof::TYPE_ICONS[$type], "Icon for '{$type}' must be a Font Awesome class");
            $this->assertIsArray(SocialProof::defaultSettingsFor($type), "Missing defaults for '{$type}'");
        }
    }

    public function test_submission_and_email_capture_types_are_valid_subsets(): void
    {
        foreach (SocialProof::SUBMISSION_TYPES as $type) {
            $this->assertArrayHasKey($type, SocialProof::TYPES, "SUBMISSION_TYPES has unknown '{$type}'");
        }
        foreach (SocialProof::EMAIL_CAPTURE_TYPES as $type) {
            $this->assertContains($type, SocialProof::SUBMISSION_TYPES, "EMAIL_CAPTURE_TYPES '{$type}' must also be a submission type");
        }
    }

    public function test_legacy_type_keys_are_unchanged(): void
    {
        $legacy = [
            'recent_activity', 'visitor_count', 'conversion_count',
            'trust_badge', 'review', 'testimonial_quote', 'inline_informational',
            'inline_conversions', 'new_feature', 'announcement_bar', 'sticky_cta',
            'cookie_consent', 'exit_offer', 'email_signup', 'countdown',
            'low_stock', 'video_popup', 'share_buttons', 'whatsapp_chat', 'custom_html',
        ];
        foreach ($legacy as $key) {
            $this->assertArrayHasKey($key, SocialProof::TYPES, "Legacy type '{$key}' was removed/renamed");
        }
    }

    public function test_retired_types_moved_to_legacy_and_stay_renderable(): void
    {
        foreach (['social_followers', 'click_to_call', 'price_drop'] as $key) {
            $this->assertArrayHasKey($key, SocialProof::LEGACY_TYPES, "Retired type '{$key}' missing from LEGACY_TYPES");
            $this->assertArrayNotHasKey($key, SocialProof::TYPES, "Retired type '{$key}' still in TYPES");
            $n = SocialProof::normalizeNotification(['type' => $key]);
            $this->assertSame($key, $n['type'], "Legacy '{$key}' must survive normalization");
            $this->assertNotSame('', $n['name']);
        }
        $this->assertEmpty(
            array_intersect_key(SocialProof::TYPES, SocialProof::LEGACY_TYPES),
            'TYPES and LEGACY_TYPES must be disjoint'
        );
    }

    public function test_new_types_are_present_in_catalog(): void
    {
        foreach (['newsletter_signup', 'informational_mini', 'informational_bar_mini'] as $key) {
            $this->assertArrayHasKey($key, SocialProof::TYPES, "New type '{$key}' missing from TYPES");
        }
        $this->assertContains('newsletter_signup', SocialProof::SUBMISSION_TYPES);
        $this->assertContains('newsletter_signup', SocialProof::EMAIL_CAPTURE_TYPES);
    }

    public function test_normalize_notification_falls_back_to_recent_activity_for_unknown_type(): void
    {
        $n = SocialProof::normalizeNotification(['type' => 'does_not_exist']);
        $this->assertSame('recent_activity', $n['type']);
        $this->assertNotEmpty($n['settings']);
        $this->assertTrue($n['is_active']);
    }

    public function test_normalize_notification_merges_defaults_for_new_types(): void
    {
        $n = SocialProof::normalizeNotification(['type' => 'survey_popup', 'settings' => ['question' => 'Hi?']]);
        $this->assertSame('survey_popup', $n['type']);
        $this->assertSame('Hi?', $n['settings']['question']);
        $this->assertArrayHasKey('options', $n['settings']);
    }
}
