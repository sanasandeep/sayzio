<?php

namespace App\Services\Brand;

/** Shared field catalogue for manual editing, AI suggestions and export. */
class BrandKitFields
{
    public const GROUPS = [
        'identity' => ['mission' => 'Mission', 'vision' => 'Vision', 'values' => 'Values', 'positioning' => 'Positioning', 'differentiators' => 'What makes you different', 'personality' => 'Brand personality', 'elevator_pitch' => 'Elevator pitch', 'short_bio' => 'Short bio', 'long_bio' => 'Long bio', 'key_messages' => 'Key messages', 'proof_points' => 'Verified proof points'],
        'guidelines' => ['logo_rules' => 'Logo spacing, minimum size and placement', 'color_rules' => 'Color combinations and usage', 'typography_rules' => 'Typography and hierarchy', 'imagery_rules' => 'Photography and illustration style', 'accessibility_rules' => 'Accessibility guidelines', 'writing_examples' => 'Writing examples', 'dos' => 'Do', 'donts' => 'Do not', 'terminology' => 'Preferred terminology', 'pronunciation' => 'Name pronunciation', 'font_license' => 'Font license and redistribution permissions'],
        'campaigns' => ['campaign_name' => 'Campaign name', 'campaign_audience' => 'Campaign audience', 'campaign_objective' => 'Campaign objective', 'campaign_offer' => 'Approved offer', 'campaign_messages' => 'Campaign messaging', 'campaign_dates' => 'Start / end dates and timezone', 'campaign_channels' => 'Approved channels', 'campaign_claims' => 'Approved claims and evidence', 'campaign_disclaimers' => 'Required disclaimers', 'campaign_restrictions' => 'Restrictions and exclusions', 'campaign_approval' => 'Approval owner and process'],
        'socials' => ['contact_email' => 'Public contact email', 'contact_phone' => 'Public contact phone', 'instagram' => 'Instagram URL', 'linkedin' => 'LinkedIn URL', 'facebook' => 'Facebook URL', 'youtube' => 'YouTube URL', 'tiktok' => 'TikTok URL', 'x_profile' => 'X URL', 'social_rules' => 'Social profile and posting guidelines'],
        'templates' => ['letterhead_details' => 'Letterhead content and layout instructions', 'business_card_details' => 'Business card details', 'email_signature' => 'Email signature text', 'social_template_rules' => 'Social post and cover instructions', 'presentation_rules' => 'Presentation instructions', 'qr_rules' => 'QR usage and placement instructions'],
    ];

    public const MEDIA = ['logo_secondary' => 'Secondary logo', 'logo_monochrome' => 'Monochrome logo', 'brand_icon' => 'Brand icon', 'background' => 'Brand background', 'pattern' => 'Brand pattern', 'letterhead' => 'Letterhead', 'business_card' => 'Business card', 'email_banner' => 'Email banner', 'social_banner' => 'Social cover', 'social_post' => 'Social post', 'presentation_cover' => 'Presentation cover', 'font_heading_file' => 'Heading font file', 'font_body_file' => 'Body font file'];

    public static function text(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }
}
