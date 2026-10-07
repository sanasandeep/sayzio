<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use Tests\TestCase;

class ProfileCardDetailsTest extends TestCase
{
    public function test_profile_details_render_in_cover_and_other_layouts_without_duplicate_links(): void
    {
        foreach (['cover_hero', 'classic_creator', 'business_card', 'glass', 'stats'] as $layout) {
            $html = view('common.biolink-profile-card', [
                'block' => new BiolinkBlock(['type' => 'profile_card_v2']),
                's' => ['name' => 'Alex Morgan', 'bio' => 'Designer', 'location' => 'Hyderabad',
                    'website' => 'https://example.com', 'cta_label' => 'Get in touch',
                    'cta_url' => 'https://example.com/contact', 'verified' => true,
                    'socials' => [['name' => 'linkedin', 'url' => 'https://linkedin.com/in/alex']],
                    '_style' => ['_profile_layout' => $layout]],
                'blockStyle' => [], 'blockInline' => '', 'fontColor' => '#172033', 'socialIcons' => [],
            ])->render();
            $this->assertStringContainsString('Hyderabad', $html, $layout);
            $this->assertStringContainsString('Get in touch', $html, $layout);
            $this->assertSame(1, substr_count($html, 'href="https://example.com"'), $layout);
            $this->assertSame(1, substr_count($html, 'href="https://linkedin.com/in/alex"'), $layout);
            $this->assertStringContainsString('fa-circle-check', $html, $layout);
        }
    }
}
