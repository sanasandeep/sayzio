<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the Dialer "Identity Profile" hub
 * (.agents/memory/dialer-everyday.md), which has three moving parts that can
 * silently drift:
 *
 *   (1) DialerIdentity::resolve()/payload() — pulls socials, map locations and
 *       reachable channels out of a matched user's biolink and layers the
 *       owner's MANUAL additions on top, kept deliberately distinct.
 *   (2) The signed `user.dialer.vcard` route — streams a shareable .vcf and
 *       MUST reject a tampered/invalid signature (the signature is the only
 *       authorization on that route).
 *   (3) POST /api/v1/contacts/{id}/manual-profile — persists + normalizes the
 *       owner's manual channels/socials/location.
 *
 * Note: we never bind `current_workspace` here. DialerIdentity resolution must
 * work without a bound workspace (the Sanctum/mobile path never binds one), so
 * the Link workspace global scope is skipped and biolink resolution is by
 * user_id/link_id only — exactly as in production.
 */
class DialerIdentityProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    /**
     * Build an active biolink for $creator seeded with a socials block, a map
     * location block, and a WhatsApp widget so extraction has something to pull.
     */
    private function seedBiolink(User $creator): Link
    {
        $bio = $creator->links()->create([
            'user_id'   => $creator->id,
            'type'      => 'biolink',
            'alias'     => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);

        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'socials',
            'is_active'  => true,
            'sort_order' => 1,
            'settings'   => ['platforms' => [
                ['name' => 'instagram', 'url' => 'https://instagram.com/creator'],
                ['name' => 'github',    'url' => 'https://github.com/creator'],
                ['name' => 'broken',    'url' => '#'],   // skipped (placeholder)
            ]],
        ]);

        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'map_location',
            'is_active'  => true,
            'sort_order' => 2,
            'settings'   => [
                'label'   => 'Studio',
                'address' => '1 Market St, San Francisco',
                'lat'     => 37.7937,
                'lng'     => -122.3965,
            ],
        ]);

        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'whatsapp-widget',
            'is_active'  => true,
            'sort_order' => 3,
            'settings'   => ['phone' => '+1 (555) 010-2030'],
        ]);

        return $bio;
    }

    // ===== (1) resolve()/payload() extraction + manual separation =====

    public function test_payload_extracts_biolink_data_and_keeps_manual_distinct(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $this->seedBiolink($creator);

        // A saved contact owned by $owner, attached to the matched Sayzio user.
        $contact = Contact::create([
            'user_id'         => $owner->id,
            'display_name'    => 'Jane Creator',
            'biolink_user_id' => $creator->id,
            'manual_profile'  => [
                'channels' => [
                    ['type' => 'telegram', 'label' => 'TG', 'value' => '@janetg'],
                ],
                'socials' => [
                    ['platform' => 'tiktok', 'label' => 'TikTok', 'url' => 'https://tiktok.com/@jane'],
                ],
                'location' => [
                    'label' => 'Home', 'address' => '500 Howard St', 'lat' => null, 'lng' => null,
                ],
            ],
        ]);
        ContactPhone::create([
            'contact_id' => $contact->id, 'label' => 'Mobile',
            'value' => '+1 555 777 8888', 'value_e164' => '+15557778888', 'is_primary' => true,
        ]);
        ContactEmail::create([
            'contact_id' => $contact->id, 'label' => 'Work',
            'value' => 'jane@example.com', 'is_primary' => true,
        ]);

        $resolved = DialerIdentity::resolve($owner, $contact->id, null);
        // The biolink owner is matched and the active biolink is found.
        $this->assertNotNull($resolved['matchedUser']);
        $this->assertSame($creator->id, $resolved['matchedUser']->id);
        $this->assertNotNull($resolved['bio']);
        // With no explicit number, it falls back to the contact's primary phone.
        $this->assertSame('+15557778888', $resolved['number']);

        $payload = DialerIdentity::payload($owner, $resolved);

        // --- Auto-pulled socials (from the biolink; '#' placeholder dropped) ---
        $socialUrls = array_column($payload['socials'], 'url');
        $this->assertContains('https://instagram.com/creator', $socialUrls);
        $this->assertContains('https://github.com/creator', $socialUrls);
        $this->assertNotContains('#', $socialUrls);
        // Auto socials are NOT tagged as manual — the two sets stay distinct.
        foreach ($payload['socials'] as $s) {
            $this->assertNotSame('manual', $s['source'] ?? null);
        }
        // The manual social does NOT leak into the auto list.
        $this->assertNotContains('https://tiktok.com/@jane', $socialUrls);

        // --- Auto-pulled location ---
        $this->assertCount(1, $payload['locations']);
        $this->assertSame('Studio', $payload['locations'][0]['label']);
        $this->assertStringContainsString('google.com/maps', $payload['locations'][0]['maps_url']);

        // --- Channels: number-derived + biolink WhatsApp widget ---
        $channelTypes = array_column($payload['channels'], 'type');
        $this->assertContains('phone', $channelTypes);
        $this->assertContains('sms', $channelTypes);
        $this->assertContains('email', $channelTypes);   // from the saved contact
        // The biolink WhatsApp widget surfaces as a channel sourced 'biolink'.
        $bioWa = collect($payload['channels'])->firstWhere('source', 'biolink');
        $this->assertNotNull($bioWa);
        $this->assertStringContainsString('wa.me/15550102030', $bioWa['url']);

        // --- Manual additions: present, normalized, and tagged source=manual ---
        $this->assertSame('https://t.me/janetg', $payload['manual']['channels'][0]['url']);
        $this->assertSame('manual', $payload['manual']['channels'][0]['source']);
        $this->assertSame('https://tiktok.com/@jane', $payload['manual']['socials'][0]['url']);
        $this->assertSame('manual', $payload['manual']['socials'][0]['source']);
        $this->assertNotNull($payload['manual']['location']);
        $this->assertSame('Home', $payload['manual']['location']['label']);
        $this->assertSame('manual', $payload['manual']['location']['source']);

        // The shareable vCard URL is wired into the payload.
        $this->assertStringContainsString('/dialer/vcard', $payload['vcard_url']);
        $this->assertStringContainsString('signature=', $payload['vcard_url']);
    }

    public function test_resolve_matches_user_by_verified_phone_without_a_saved_contact(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $this->seedBiolink($creator);

        $creator->linkedIdentifiers()->create([
            'kind'        => 'phone',
            'value'       => '+15551234567',
            'verified_at' => now(),
        ]);

        // No contactId — resolve purely from the dialed number.
        $resolved = DialerIdentity::resolve($owner, null, '+1 (555) 123-4567');
        $this->assertNull($resolved['contact']);
        $this->assertNotNull($resolved['matchedUser']);
        $this->assertSame($creator->id, $resolved['matchedUser']->id);
        $this->assertNotNull($resolved['bio']);

        $payload = DialerIdentity::payload($owner, $resolved);
        // Even without a contact, biolink socials still come through.
        $this->assertNotEmpty($payload['socials']);
    }

    // ===== (2) signed vcard route streams a vcf + rejects bad signatures =====

    public function test_signed_vcard_route_streams_valid_vcard(): void
    {
        $owner   = $this->makeUser('owner');
        $creator = $this->makeUser('creator');
        $this->seedBiolink($creator);

        $contact = Contact::create([
            'user_id'         => $owner->id,
            'display_name'    => 'Jane Creator',
            'organization'    => 'Acme',
            'job_title'       => 'Designer',
            'biolink_user_id' => $creator->id,
        ]);
        ContactPhone::create([
            'contact_id' => $contact->id, 'label' => 'Mobile',
            'value' => '+15557778888', 'value_e164' => '+15557778888', 'is_primary' => true,
        ]);
        ContactEmail::create([
            'contact_id' => $contact->id, 'label' => 'Work',
            'value' => 'jane@example.com', 'is_primary' => true,
        ]);

        $url = DialerIdentity::vcardUrl($owner, $contact, '+15557778888');

        $resp = $this->get($url);
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/vcard; charset=utf-8');
        $resp->assertHeader('Content-Disposition', 'attachment; filename="jane-creator.vcf"');

        $body = $resp->getContent();
        $this->assertStringContainsString('BEGIN:VCARD', $body);
        $this->assertStringContainsString('VERSION:3.0', $body);
        $this->assertStringContainsString('END:VCARD', $body);
        // The dialed number and the matched biolink make it into the card.
        $this->assertStringContainsString('+15557778888', $body);
        $this->assertStringContainsString('instagram.com/creator', $body);
    }

    public function test_vcard_route_rejects_tampered_signature(): void
    {
        $owner = $this->makeUser('owner');
        $contact = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Solo Contact',
        ]);

        $valid = DialerIdentity::vcardUrl($owner, $contact, '+15557778888');
        // Appending an unsigned parameter changes the payload the signature was
        // computed over, so the `signed` middleware must reject it (HTTP 403).
        $tampered = $valid . '&tampered=1';

        $this->get($tampered)->assertForbidden();
    }

    public function test_vcard_route_rejects_missing_signature(): void
    {
        $owner = $this->makeUser('owner');
        // Hitting the bare route with no signature at all is forbidden.
        $this->get(route('user.dialer.vcard', ['u' => $owner->id]))->assertForbidden();
    }

    // ===== (3) manual-profile API persists + normalizes =====

    private function asUser(User $user): self
    {
        // Real Sanctum token (see .agents/memory/sanctum-api-tests.md): the
        // TouchSessionToken middleware can't operate on a Sanctum::actingAs mock.
        $this->withToken($user->createToken('dialer-test')->plainTextToken);
        return $this;
    }

    public function test_manual_profile_api_persists_and_normalizes(): void
    {
        $owner = $this->makeUser('owner');
        $contact = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Jane Creator',
        ]);

        $resp = $this->asUser($owner)->postJson('/api/v1/contacts/' . $contact->id . '/manual-profile', [
            'channels' => [
                ['type' => 'whatsapp', 'label' => '', 'value' => ' +1 (555) 222-3333 '],
                ['type' => 'telegram', 'label' => 'TG', 'value' => '@myhandle'],
                ['type' => 'custom',   'label' => 'Skip', 'value' => ''], // dropped (empty value)
            ],
            'socials' => [
                ['platform' => 'instagram', 'label' => 'IG', 'url' => 'https://instagram.com/me'],
                ['platform' => '',          'label' => '',   'url' => ''], // dropped (empty url)
            ],
            'location' => [
                'label' => 'HQ', 'address' => '123 Main St', 'lat' => '12.34', 'lng' => '56.78',
            ],
        ]);

        $resp->assertOk();

        // --- Response is fully normalized (derived url/source/maps_url added) ---
        $resp->assertJsonCount(2, 'data.manual_profile.channels');
        $resp->assertJsonPath('data.manual_profile.channels.0.type', 'whatsapp');
        // Empty label backfills from the type.
        $resp->assertJsonPath('data.manual_profile.channels.0.label', 'Whatsapp');
        $resp->assertJsonPath('data.manual_profile.channels.0.source', 'manual');
        // Whitespace trimmed + digits-only WhatsApp deep link computed.
        $resp->assertJsonPath('data.manual_profile.channels.0.value', '+1 (555) 222-3333');
        $resp->assertJsonPath('data.manual_profile.channels.0.url', 'https://wa.me/15552223333');
        $resp->assertJsonPath('data.manual_profile.channels.1.url', 'https://t.me/myhandle');

        $resp->assertJsonCount(1, 'data.manual_profile.socials');
        $resp->assertJsonPath('data.manual_profile.socials.0.url', 'https://instagram.com/me');
        $resp->assertJsonPath('data.manual_profile.socials.0.source', 'manual');

        $resp->assertJsonPath('data.manual_profile.location.label', 'HQ');
        $resp->assertJsonPath('data.manual_profile.location.source', 'manual');
        $this->assertStringContainsString(
            'google.com/maps',
            $resp->json('data.manual_profile.location.maps_url')
        );

        // --- DB stores ONLY raw editable fields (no derived url/source/maps_url) ---
        $raw = $contact->fresh()->manual_profile;
        $this->assertCount(2, $raw['channels']);
        $this->assertArrayNotHasKey('url', $raw['channels'][0]);
        $this->assertArrayNotHasKey('source', $raw['channels'][0]);
        $this->assertSame('+1 (555) 222-3333', $raw['channels'][0]['value']);
        $this->assertArrayNotHasKey('maps_url', $raw['location']);
        $this->assertSame(12.34, $raw['location']['lat']);
        $this->assertSame(56.78, $raw['location']['lng']);
    }

    public function test_manual_profile_api_rejects_a_contact_owned_by_someone_else(): void
    {
        $owner     = $this->makeUser('owner');
        $stranger  = $this->makeUser('stranger');
        $foreign = Contact::create([
            'user_id'      => $stranger->id,
            'display_name' => 'Not Yours',
        ]);

        $resp = $this->asUser($owner)->postJson('/api/v1/contacts/' . $foreign->id . '/manual-profile', [
            'channels' => [['type' => 'telegram', 'value' => '@x']],
        ]);

        $resp->assertNotFound();
        $resp->assertJsonPath('error.code', 'not_found');
        // The stranger's contact is untouched.
        $this->assertNull($foreign->fresh()->manual_profile);
    }
}
