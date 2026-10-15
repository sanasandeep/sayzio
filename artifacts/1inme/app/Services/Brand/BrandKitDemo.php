<?php

namespace App\Services\Brand;

/** Local sample content; never creates a kit or calls a provider. */
class BrandKitDemo
{
    public static function name(): string
    {
        return 'Northstar Studio · Demo';
    }

    public static function form(): array
    {
        $c = self::config();
        $form = ['name' => self::name(), 'website' => $c['website_url'], 'primary' => $c['palette']['primary'], 'secondary' => $c['palette']['secondary'], 'accent' => $c['palette']['accent'], 'heading' => $c['fonts']['heading'], 'body' => $c['fonts']['body'], 'tone' => $c['voice']['tone'], 'taglines' => implode("\n", $c['taglines'])];
        foreach (['description', 'logo', 'logo_light', 'logo_dark', 'favicon', 'bio', 'audience', 'instructions', 'exclusions', 'block_theme', 'approval_status', ...array_keys(BrandKitFields::text() + BrandKitFields::MEDIA)] as $key) {
            $form[$key] = $c[$key] ?? '';
        }

        return $form;
    }

    public static function config(): array
    {
        $c = ['description' => 'Demo brand: an independent creative studio turning growing ideas into clear, confident identities.', 'bio' => 'We help small teams tell their story through thoughtful design, clear words and useful digital experiences. Demo content — make this your own.', 'website_url' => 'https://example.com', 'palette' => ['primary' => '#3454EF', 'secondary' => '#8B5CF6', 'accent' => '#F59E0B'], 'fonts' => ['heading' => 'Space Grotesk', 'body' => 'Inter'], 'voice' => ['tone' => 'Clear, warm, thoughtful and confident'], 'taglines' => ['Clear ideas. Thoughtful design.', 'Make your next idea shine.'], 'audience' => 'Demo audience: startup founders and independent businesses building their first brand.', 'instructions' => 'Use plain language, short sentences and concrete benefits. Replace all demo facts before publishing.', 'exclusions' => 'Avoid unsupported promises, invented statistics and claims of guaranteed results.', 'block_theme' => 'minimal', 'approval_status' => 'draft'];
        foreach (BrandKitFields::text() as $key => $label) {
            $c[$key] = 'Demo '.$label.': replace this example with your approved brand details.';
        }
        $c = array_replace($c, [
            'mission' => 'Help growing teams communicate clearly through thoughtful design.', 'vision' => 'A world where useful ideas are easier to understand.', 'values' => "Clarity before complexity.\nCare in every detail.\nCuriosity and honest collaboration.",
            'positioning' => 'A practical creative partner for small teams that want a clear, distinctive brand.', 'differentiators' => 'A considered approach combining identity, messaging and useful everyday templates.', 'personality' => 'Thoughtful, curious, approachable and quietly confident.', 'elevator_pitch' => 'Northstar Studio helps small teams turn their ideas into a clear brand and a confident digital presence.', 'short_bio' => 'Thoughtful design for growing ideas.', 'long_bio' => 'Northstar Studio is a fictional demo brand. This space shows how your longer brand story can appear in a guide or on a public brand page. Replace it with your real story, experience and services.', 'key_messages' => "Start with clarity.\nMake every touchpoint feel connected.\nBuild a brand people can understand.", 'proof_points' => 'Add verified work, testimonials or evidence here. This demo makes no factual performance claims.',
            'logo_rules' => 'Keep clear space equal to the star symbol width around the logo. Use the dark variant on light surfaces and the light variant on dark surfaces. Do not stretch or rotate the mark.',
            'color_rules' => 'Use blue for primary actions, violet for supporting accents and amber sparingly for emphasis. Keep body text dark on light backgrounds.',
            'typography_rules' => 'Space Grotesk for headings. Inter for body copy. Keep headings concise and use a comfortable body line height.',
            'imagery_rules' => 'Use generous space, simple geometric forms and purposeful composition. Replace these demo illustrations with your own photography or artwork.',
            'accessibility_rules' => 'Check text contrast, provide descriptive image text and keep important information available as readable text.',
            'writing_examples' => "Prefer: Tell your story clearly.\nAvoid: Revolutionize your entire world overnight.", 'dos' => "Use clear, useful language.\nKeep the logo proportional.\nUse approved brand colors.", 'donts' => "Do not invent claims or statistics.\nDo not distort the logo.\nDo not present demo details as real business information.", 'terminology' => 'Prefer studio, clarity, thoughtful design and collaboration.', 'pronunciation' => 'North-star Studio', 'font_license' => 'Demo font pairing: Space Grotesk and Inter. Font binaries are not bundled with the demo. Verify licenses before uploading or redistributing font files.',
            'contact_email' => 'hello@example.com', 'contact_phone' => '+1 202-555-0142', 'instagram' => 'https://example.com/instagram', 'linkedin' => 'https://example.com/linkedin', 'facebook' => 'https://example.com/facebook', 'youtube' => 'https://example.com/youtube', 'tiktok' => 'https://example.com/tiktok', 'x_profile' => 'https://example.com/social',
            'campaign_name' => 'Demo: A clearer first impression', 'campaign_audience' => 'Independent business owners refreshing their visual identity.', 'campaign_objective' => 'Introduce the studio and invite visitors to explore its work.', 'campaign_offer' => 'Demo only — add a real approved offer, if any.', 'campaign_messages' => 'A clear story. A confident look. A consistent experience.', 'campaign_dates' => 'Demo dates — set your actual campaign window and timezone.', 'campaign_channels' => 'Website, Link in Bio, email and selected social profiles.', 'campaign_claims' => 'Use only claims your team can support with evidence. No performance guarantees are provided by this demo.', 'campaign_disclaimers' => 'Demo guidance only. Add the disclosures required for your actual campaign.', 'campaign_restrictions' => 'Replace with your actual eligibility, geography and offer restrictions.', 'campaign_approval' => 'Have the campaign owner review claims, dates, imagery and required disclosures before publication.',
            'letterhead_details' => 'Logo in the top-left, a quiet blue rule and contact details in the footer. Keep the body area clear.', 'business_card_details' => 'Name, role, public email and website on a clean dark surface with the star mark.', 'email_signature' => "Alex Morgan | Creative Director\nNorthstar Studio — Demo\nhello@example.com | example.com", 'social_template_rules' => 'Use one clear message, ample space and a restrained accent. Replace demo copy and images before posting.', 'presentation_rules' => 'Use a strong title, simple section headings and readable charts. Use only verified data.', 'qr_rules' => 'Keep a clear margin, preserve contrast and test scanning on several devices before printing.',
        ]);
        foreach (['logo', 'logo_light', 'logo_dark', 'favicon', ...array_keys(BrandKitFields::MEDIA)] as $key) {
            if (str_starts_with($key, 'font_')) {
                $c[$key] = '';

                continue;
            }
            $c[$key] = asset('brand-demo/'.str_replace('_', '-', $key).'.svg');
        }

        return $c;
    }
}
