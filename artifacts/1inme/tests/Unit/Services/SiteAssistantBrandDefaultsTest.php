<?php

namespace Tests\Unit\Services;

use App\Services\AI\SiteAssistantSettings;
use Tests\TestCase;

/**
 * Pins the assistant's default branding strings to "Ask Zio". The chat
 * widget header, greeting bubble, and "…is typing…" indicator must all
 * resolve to the Ask Zio brand when an admin hasn't supplied an override,
 * and admin overrides must still win. A regression here silently reverts
 * the widget to a stale brand name on every install that hasn't customised
 * it, and the two front-ends (blade + marketing React) would drift apart.
 */
class SiteAssistantBrandDefaultsTest extends TestCase
{
    public function test_default_brand_name_is_ask_zio(): void
    {
        $this->assertSame('Ask Zio', SiteAssistantSettings::DEFAULT_BRAND_NAME);
        $this->assertSame('Ask Zio', SiteAssistantSettings::brandNameFor([]));
        $this->assertSame('Ask Zio', SiteAssistantSettings::brandNameFor(['brand_name' => '  ']));
    }

    public function test_admin_brand_name_override_wins(): void
    {
        $this->assertSame(
            'Helper Bot',
            SiteAssistantSettings::brandNameFor(['brand_name' => 'Helper Bot'])
        );
    }

    public function test_default_typing_indicator_is_branded(): void
    {
        $this->assertSame('Ask Zio is typing…', SiteAssistantSettings::DEFAULT_TYPING);
        $this->assertSame('Ask Zio is typing…', SiteAssistantSettings::typingIndicatorFor([]));
    }

    public function test_admin_typing_indicator_override_wins(): void
    {
        $this->assertSame(
            'One moment…',
            SiteAssistantSettings::typingIndicatorFor(['assistant_typing' => 'One moment…'])
        );
    }

    public function test_seeded_default_greeting_mentions_ask_zio(): void
    {
        $defaults = SiteAssistantSettings::defaults();

        $this->assertArrayHasKey('greeting', $defaults);
        $this->assertStringContainsString('Ask Zio', $defaults['greeting']);
        $this->assertStringNotContainsString('Zio Bot', $defaults['greeting']);
    }
}
