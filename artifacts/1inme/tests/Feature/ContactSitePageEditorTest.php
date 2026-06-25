<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature coverage for the /contact page editor (admin) and the public
 * /contact render. Mirrors AboutSitePageEditorTest so every editable
 * group on the Contact page (hero, contact details, social, map,
 * feature_cards, office_image, form copy, details_heading) survives a
 * full save → render round-trip and so the admin form keeps exposing
 * all the inputs the public template depends on.
 *
 * In addition, the Contact `extra` payload is structurally tricky:
 * lat/long must be clamped to valid ranges, zoom must be clamped to
 * 1–19, blank text fields must collapse to empty strings rather than
 * the seeded Hyderabad defaults, and the social link sub-array must
 * round-trip per platform with a strict five-platform whitelist. These
 * tests guard against a future schema change silently regressing any of
 * those guarantees, and against a future edit to
 * SitePagesContent::normalizeContactExtra, the controller validation
 * rules, the contact-editor partial, or the public contact.blade.php
 * silently breaking hero / feature cards / form copy without anyone
 * noticing until users complain.
 */
class ContactSitePageEditorTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makeContactPage(): SitePage
    {
        // The Contact page is seeded by an earlier migration — refresh
        // its content here so each test starts from a known shape.
        $page = SitePage::firstOrNew(['slug' => 'contact']);
        $page->fill([
            'title'            => 'Contact us',
            'meta_description' => 'Seeded contact page.',
            'sections'         => SitePagesContent::contactSectionsDefault(),
            'extra'            => SitePagesContent::contactExtraDefault(),
        ])->save();
        return $page;
    }

    /**
     * Minimal-but-valid Contact update payload. Each test overrides the
     * `extra` block with the values it needs to exercise.
     */
    private function payload(array $extra): array
    {
        return [
            'title'            => 'Contact us',
            'meta_description' => 'Updated contact description.',
            'sections'         => [
                ['id' => '', 'heading' => 'We love hearing from you', 'body' => 'Drop us a note.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => $extra,
        ];
    }

    /**
     * A fully-populated payload that touches every editable group on
     * the Contact editor. Used by the round-trip and public-render
     * assertions so a single payload exercises hero, contact details,
     * social, map, feature_cards, office_image, form copy, and the
     * details_heading field.
     */
    private function fullPayload(): array
    {
        return [
            'title'            => 'Contact Sayzio — Edited',
            'meta_description' => 'Updated contact description.',
            'sections'         => [
                ['id' => '', 'heading' => 'We love hearing from you', 'body' => 'Drop us a note.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => [
                'hero' => [
                    'badge_label'       => '  Say hello  ', // whitespace must be trimmed
                    'badge_icon'        => 'fa-comment',
                    'availability_text' => 'Replies in under an hour',
                    'availability_icon' => 'fa-bolt',
                    'languages'         => 'EN · हिन्दी · తెలుగు',
                    'side_image'        => '/img/custom-contact-hero.png',
                    'side_image_alt'    => 'Custom contact hero alt',
                    'floating_card' => [
                        'title'    => 'Real humans',
                        'subtitle' => 'No bots, ever',
                        'icon'     => 'fa-headset',
                    ],
                ],
                'address' => "Custom Address Line 1\nCustom Address Line 2",
                'email'   => 'support@example.com',
                'phone'   => '+91 99 1234 5678',
                'hours'   => "Mon–Fri 9–6",
                'social'  => [
                    'twitter'   => 'https://twitter.com/custom',
                    'instagram' => 'https://instagram.com/custom',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                ],
                'map' => [
                    'lat'   => 12.9716,
                    'lng'   => 77.5946,
                    'zoom'  => 13,
                    'label' => 'Bengaluru office',
                ],
                'details_heading' => 'Reach us directly',
                'feature_cards' => [
                    ['icon' => 'fa-bolt',      'title' => 'Quick replies', 'desc' => 'Real humans within hours.'],
                    // fully-blank repeater row must be dropped
                    ['icon' => '',             'title' => '',              'desc' => ''],
                    ['icon' => 'fa-handshake', 'title' => 'Partnerships',  'desc' => 'Pitch us — we read every one.'],
                ],
                'office_image' => [
                    'url' => '/img/custom-office.png',
                    'alt' => 'Our custom office',
                ],
                'form' => [
                    'heading'             => 'Drop us a message',
                    'intro'               => 'A real person will read this.',
                    'name_label'          => 'Full name',
                    'name_placeholder'    => 'Your name',
                    'email_label'         => 'Email address',
                    'email_placeholder'   => 'you@example.com',
                    'subject_label'       => 'What is it about?',
                    'subject_placeholder' => 'Subject line',
                    'message_label'       => 'Your message',
                    'message_placeholder' => 'Type away…',
                    'submit_label'        => 'Send it',
                ],
            ],
        ];
    }

    public function test_admin_edit_screen_renders_every_new_contact_field(): void
    {
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.site-pages.edit', 'contact'));
        $resp->assertOk();

        // Hero block inputs.
        $resp->assertSee('name="extra[hero][badge_label]"',           false);
        $resp->assertSee('name="extra[hero][badge_icon]"',            false);
        $resp->assertSee('name="extra[hero][availability_text]"',     false);
        $resp->assertSee('name="extra[hero][availability_icon]"',     false);
        $resp->assertSee('name="extra[hero][languages]"',             false);
        $resp->assertSee('name="extra[hero][side_image]"',            false);
        $resp->assertSee('name="extra[hero][side_image_alt]"',        false);
        $resp->assertSee('name="extra[hero][floating_card][title]"',    false);
        $resp->assertSee('name="extra[hero][floating_card][subtitle]"', false);
        $resp->assertSee('name="extra[hero][floating_card][icon]"',     false);

        // Contact details / heading.
        $resp->assertSee('name="extra[details_heading]"', false);
        $resp->assertSee('name="extra[address]"',         false);
        $resp->assertSee('name="extra[email]"',           false);
        $resp->assertSee('name="extra[phone]"',           false);
        $resp->assertSee('name="extra[hours]"',           false);

        // Social links.
        $resp->assertSee('name="extra[social][twitter]"',   false);
        $resp->assertSee('name="extra[social][instagram]"', false);
        $resp->assertSee('name="extra[social][linkedin]"',  false);
        $resp->assertSee('name="extra[social][youtube]"',   false);
        $resp->assertSee('name="extra[social][facebook]"',  false);

        // Map block.
        $resp->assertSee('name="extra[map][lat]"',   false);
        $resp->assertSee('name="extra[map][lng]"',   false);
        $resp->assertSee('name="extra[map][zoom]"',  false);
        $resp->assertSee('name="extra[map][label]"', false);

        // Office image and form copy. These use Alpine bindings (`:name="…"`)
        // for the repeater, so we assert on the Alpine-bound name template
        // for feature_cards rather than a literal `name="extra[…]"` attr.
        $resp->assertSee('extra[feature_cards]',     false);
        $resp->assertSee('name="extra[office_image][url]"', false);
        $resp->assertSee('name="extra[office_image][alt]"', false);
        $resp->assertSee('name="extra[form][heading]"',             false);
        $resp->assertSee('name="extra[form][intro]"',               false);
        $resp->assertSee('name="extra[form][name_label]"',          false);
        $resp->assertSee('name="extra[form][name_placeholder]"',    false);
        $resp->assertSee('name="extra[form][email_label]"',         false);
        $resp->assertSee('name="extra[form][email_placeholder]"',   false);
        $resp->assertSee('name="extra[form][subject_label]"',       false);
        $resp->assertSee('name="extra[form][subject_placeholder]"', false);
        $resp->assertSee('name="extra[form][message_label]"',       false);
        $resp->assertSee('name="extra[form][message_placeholder]"', false);
        $resp->assertSee('name="extra[form][submit_label]"',        false);
    }

    public function test_admin_save_round_trips_full_contact_extra(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $payload = $this->payload([
            'address' => "  Sayzio HQ\n42 Example Street  ", // outer whitespace must be trimmed
            'email'   => '  hello@example.com  ',
            'phone'   => '  +91 40 9876 5432  ',
            'hours'   => "Mon–Fri · 09:00 – 17:00",
            'social'  => [
                'twitter'   => 'https://twitter.com/onein',
                'instagram' => 'https://instagram.com/onein',
                'linkedin'  => 'https://linkedin.com/company/onein',
                'youtube'   => 'https://youtube.com/@onein',
                'facebook'  => 'https://facebook.com/onein',
            ],
            'map' => [
                'lat'   => 12.9716,
                'lng'   => 77.5946,
                'zoom'  => 12,
                'label' => '  Bengaluru office  ',
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'contact'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);

        // The persisted `extra` should equal what normalizeContactExtra
        // produces for the same input — i.e. a true round-trip. The
        // controller may add a `blog_block` sub-key from a separate
        // input — strip it here so we compare the Contact-specific shape.
        $expected = SitePagesContent::normalizeContactExtra($payload['extra']);
        $stored = $page->extra;
        unset($stored['blog_block']);
        $this->assertEquals($expected, $stored);

        // Specific guarantees: trims wrapping whitespace on text fields
        // and on the map label, but preserves embedded newlines so
        // multi-line addresses render correctly on the public page.
        $this->assertSame("Sayzio HQ\n42 Example Street", $stored['address']);
        $this->assertSame('hello@example.com',           $stored['email']);
        $this->assertSame('+91 40 9876 5432',            $stored['phone']);
        $this->assertSame('Bengaluru office',            $stored['map']['label']);

        // Every supported social platform round-trips per platform —
        // a future refactor that drops one (e.g. by hard-coding a
        // shorter whitelist) would fail here.
        $this->assertSame([
            'twitter'   => 'https://twitter.com/onein',
            'instagram' => 'https://instagram.com/onein',
            'linkedin'  => 'https://linkedin.com/company/onein',
            'youtube'   => 'https://youtube.com/@onein',
            'facebook'  => 'https://facebook.com/onein',
        ], $stored['social']);

        // Map coordinates and zoom inside their valid windows survive
        // unchanged (no accidental clamp).
        $this->assertSame(12.9716, $stored['map']['lat']);
        $this->assertSame(77.5946, $stored['map']['lng']);
        $this->assertSame(12,      $stored['map']['zoom']);
    }

    public function test_admin_save_round_trips_every_contact_extra_group(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $payload = $this->fullPayload();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload);
        $resp->assertRedirect(route('admin.site-pages.edit', 'contact'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);

        // The persisted `extra` should equal what normalizeContactExtra
        // produces for the same input — i.e. a true round-trip.
        $expected = SitePagesContent::normalizeContactExtra($payload['extra']);
        $stored = $page->extra;
        // The controller may add a `blog_block` sub-key from a separate
        // input — strip it here so we compare the contact-specific shape.
        unset($stored['blog_block']);
        $this->assertEquals($expected, $stored);

        // Specific guarantees: trims whitespace, drops fully-blank
        // repeater rows, preserves order.
        $this->assertSame('Say hello', $stored['hero']['badge_label']);
        $this->assertSame('Real humans', $stored['hero']['floating_card']['title']);
        $this->assertSame('Reach us directly', $stored['details_heading']);
        $this->assertCount(2, $stored['feature_cards']); // blank row dropped
        $this->assertSame(
            ['Quick replies', 'Partnerships'],
            array_column($stored['feature_cards'], 'title')
        );
        $this->assertSame('/img/custom-office.png', $stored['office_image']['url']);
        $this->assertSame('Drop us a message', $stored['form']['heading']);
        $this->assertSame('Send it', $stored['form']['submit_label']);

        // Map values are clamped/coerced and round-trip cleanly.
        $this->assertSame(12.9716, $stored['map']['lat']);
        $this->assertSame(77.5946, $stored['map']['lng']);
        $this->assertSame(13,      $stored['map']['zoom']);
        $this->assertSame('Bengaluru office', $stored['map']['label']);

        // Caps: normalizeContactExtra hard-caps the repeater leaf
        // fields and string lengths so a future schema change can't
        // push 10kb of text into the layout.
        $longInput = [
            'hero' => [
                'badge_label'       => str_repeat('a', 200),
                'availability_text' => str_repeat('b', 400),
            ],
            'feature_cards' => [
                ['icon' => 'fa-bolt', 'title' => str_repeat('c', 400), 'desc' => str_repeat('d', 800)],
            ],
            'form' => [
                'heading'      => str_repeat('e', 400),
                'submit_label' => str_repeat('f', 200),
            ],
        ];
        $longOut = SitePagesContent::normalizeContactExtra($longInput);
        $this->assertSame(60,  mb_strlen($longOut['hero']['badge_label']));
        $this->assertSame(200, mb_strlen($longOut['hero']['availability_text']));
        $this->assertSame(200, mb_strlen($longOut['feature_cards'][0]['title']));
        $this->assertSame(500, mb_strlen($longOut['feature_cards'][0]['desc']));
        $this->assertSame(200, mb_strlen($longOut['form']['heading']));
        $this->assertSame(80,  mb_strlen($longOut['form']['submit_label']));

        // 6-card cap on feature_cards.
        $manyCards = ['feature_cards' => array_fill(0, 10, ['icon' => 'fa-x', 'title' => 't', 'desc' => 'd'])];
        $cappedOut = SitePagesContent::normalizeContactExtra($manyCards);
        $this->assertCount(6, $cappedOut['feature_cards']);

        // Public /contact reflects every saved value.
        $publicResp = $this->get('/contact');
        $publicResp->assertOk();
        $publicResp->assertSee('Say hello', false);                    // hero badge
        $publicResp->assertSee('Replies in under an hour', false);     // availability
        $publicResp->assertSee('EN · हिन्दी · తెలుగు', false);        // languages
        $publicResp->assertSee('/img/custom-contact-hero.png', false); // hero side image
        $publicResp->assertSee('Real humans', false);                  // floating card
        $publicResp->assertSee('Reach us directly', false);            // details heading
        $publicResp->assertSee('Custom Address Line 1', false);        // address
        $publicResp->assertSee('support@example.com', false);          // email
        $publicResp->assertSee('+91 99 1234 5678', false);             // phone
        $publicResp->assertSee('Bengaluru office', false);             // map label
        $publicResp->assertSee('https://twitter.com/custom', false);   // social
        $publicResp->assertSee('Quick replies', false);                // feature card 1
        $publicResp->assertSee('Partnerships', false);                 // feature card 2
        $publicResp->assertSee('/img/custom-office.png', false);       // office image
        $publicResp->assertSee('Drop us a message', false);            // form heading
        $publicResp->assertSee('A real person will read this.', false);// form intro
        $publicResp->assertSee('Full name', false);                    // form name label
        $publicResp->assertSee('Send it', false);                      // submit label
    }

    public function test_blank_contact_text_fields_collapse_to_empty_strings(): void
    {
        // Even though the Contact page has seeded defaults (Hyderabad
        // address/email/phone/hours), clearing those fields in the
        // editor must persist as empty strings — not silently restore
        // the seed values. Otherwise an admin can never empty a field.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '   ',  // whitespace-only collapses to ''
                'email'   => '',
                'phone'   => '',
                'hours'   => '',
                'social'  => [
                    'twitter'   => '',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                ],
                'map' => [
                    'lat'   => 0,
                    'lng'   => 0,
                    'zoom'  => 5,
                    'label' => '',
                ],
            ]));

        $resp->assertRedirect(route('admin.site-pages.edit', 'contact'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $stored = $page->extra;
        $this->assertSame('', $stored['address']);
        $this->assertSame('', $stored['email']);
        $this->assertSame('', $stored['phone']);
        $this->assertSame('', $stored['hours']);
        $this->assertSame('', $stored['map']['label']);
        // Social links collapse per platform — none should fall back
        // to the seeded URL.
        foreach (['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'] as $k) {
            $this->assertSame('', $stored['social'][$k], "social.$k should collapse to empty string");
        }
    }

    public function test_out_of_range_lat_long_and_zoom_get_clamped(): void
    {
        // The controller's `between:` validation rejects flagrantly
        // bad numerics, so this test exercises the normaliser layer
        // directly: even if a future refactor loosens the controller
        // rule, normalizeContactExtra itself must clamp lat/long/zoom
        // so the public page never receives invalid coordinates.
        $clampedHigh = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 999.0,  'lng' => 999.0,  'zoom' => 99],
        ]);
        $this->assertSame(90.0,  $clampedHigh['map']['lat']);
        $this->assertSame(180.0, $clampedHigh['map']['lng']);
        $this->assertSame(19,    $clampedHigh['map']['zoom']);

        $clampedLow = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => -999.0, 'lng' => -999.0, 'zoom' => 0],
        ]);
        $this->assertSame(-90.0,  $clampedLow['map']['lat']);
        $this->assertSame(-180.0, $clampedLow['map']['lng']);
        $this->assertSame(1,      $clampedLow['map']['zoom']);

        // Non-numeric coordinates fall back to the seeded defaults
        // rather than silently becoming 0/0 (which would render an
        // empty ocean tile on the public map).
        $defaults = SitePagesContent::contactExtraDefault();
        $missing = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 'not-a-number', 'lng' => null, 'zoom' => 'nope'],
        ]);
        $this->assertSame((float) $defaults['map']['lat'],  $missing['map']['lat']);
        $this->assertSame((float) $defaults['map']['lng'],  $missing['map']['lng']);
        $this->assertSame((int)   $defaults['map']['zoom'], $missing['map']['zoom']);

        // End-to-end persistence check: a payload at the very edge of
        // the controller's `between:` rule (lat=90, lng=-180, zoom=19)
        // survives validation and lands intact in site_pages.extra.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();
        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [],
                'map'     => ['lat' => 90.0, 'lng' => -180.0, 'zoom' => 19, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        // Cast to float to absorb the JSON int-vs-float round-trip
        // (Postgres' JSON column may decode 90.0 as 90); the important
        // guarantee is that the extreme valid values survive the
        // controller's `between:` rule and the normaliser's clamp.
        $this->assertSame(90.0,   (float) $page->extra['map']['lat']);
        $this->assertSame(-180.0, (float) $page->extra['map']['lng']);
        $this->assertSame(19,     (int)   $page->extra['map']['zoom']);
    }

    public function test_unknown_social_platforms_are_dropped(): void
    {
        // The normaliser only persists the five known platforms — any
        // bonus key submitted by a tampered form must be discarded so
        // the public Blade view always sees a predictable shape.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [
                    'twitter'   => 'https://twitter.com/onein',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                    // The controller's validator only whitelists the five
                    // known keys; an unknown sub-key is silently ignored
                    // by Laravel's `array` rule, then dropped here too.
                    'tiktok'    => 'https://tiktok.com/@onein',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(
            ['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'],
            array_keys($page->extra['social'])
        );
        $this->assertArrayNotHasKey('tiktok', $page->extra['social']);
    }

    public function test_post_submit_messages_round_trip_through_admin_save(): void
    {
        // Task #782 — admins can edit the green success flash and (optionally)
        // the per-field "required" validation wording. All five fields must
        // round-trip through the admin save: trimmed, length-capped, and
        // stored under extra.messages so the public submission flow can pick
        // them up.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [],
                'map'     => ['lat' => 0, 'lng' => 0, 'zoom' => 5, 'label' => ''],
                'messages' => [
                    'success'          => '  We got it — talk soon!  ',
                    'name_required'    => 'Tell us your name.',
                    'email_required'   => 'We need an email to reply.',
                    'subject_required' => '',  // blank = fall back to Laravel default
                    'message_required' => 'Add a short message before sending.',
                ],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $stored = $page->extra['messages'] ?? null;
        $this->assertIsArray($stored);
        // Outer whitespace is trimmed but the message is otherwise preserved.
        $this->assertSame('We got it — talk soon!',         $stored['success']);
        $this->assertSame('Tell us your name.',             $stored['name_required']);
        $this->assertSame('We need an email to reply.',     $stored['email_required']);
        $this->assertSame('',                                $stored['subject_required']);
        $this->assertSame('Add a short message before sending.', $stored['message_required']);
    }

    public function test_oversize_post_submit_messages_are_rejected_by_validation(): void
    {
        // The success message has a 500-char rule and each per-field
        // override has a 200-char rule. Oversize input must fail
        // validation rather than silently truncating server-side.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [],
                'map'     => ['lat' => 0, 'lng' => 0, 'zoom' => 5, 'label' => ''],
                'messages' => [
                    'success'        => str_repeat('x', 501),
                    'email_required' => str_repeat('y', 201),
                ],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.messages.success',        $errors);
        $this->assertArrayHasKey('extra.messages.email_required', $errors);
    }

    public function test_contact_submit_uses_admin_success_message(): void
    {
        // End-to-end: a real POST /contact with valid input must flash
        // the admin-configured success message (not the hardcoded
        // default sentence) when extra.messages.success is set.
        $page = $this->makeContactPage();
        $extra = $page->extra;
        $extra['messages']['success'] = 'Custom success — heard you!';
        $page->extra = $extra;
        $page->save();

        $resp = $this->from('/contact')->post('/contact', [
            'name'    => 'Visitor',
            'email'   => 'visitor@example.com',
            'subject' => 'Hello',
            'message' => 'A short note from a visitor.',
        ]);

        $resp->assertRedirect('/contact');
        $resp->assertSessionHas('success', 'Custom success — heard you!');
    }

    public function test_contact_submit_falls_back_to_default_success_when_blank(): void
    {
        // When the admin has not customized the success message (or has
        // wiped it back to blank), the controller's literal default
        // sentence is used so the page still renders a friendly flash.
        $this->makeContactPage(); // seeded extra has messages.success === ''

        $resp = $this->from('/contact')->post('/contact', [
            'name'    => 'Visitor',
            'email'   => 'visitor@example.com',
            'subject' => 'Hello',
            'message' => 'A short note from a visitor.',
        ]);

        $resp->assertRedirect('/contact');
        $resp->assertSessionHas('success', 'Thanks! Your message has been sent. We will reply within one business day.');
    }

    public function test_contact_submit_uses_admin_required_field_message_only_when_set(): void
    {
        // Mixed override: admin set a custom email-required message but
        // left name/subject/message blank. Submitting an empty form
        // must produce the custom email line plus Laravel-default
        // phrasing for the other three fields.
        $page = $this->makeContactPage();
        $extra = $page->extra;
        $extra['messages']['email_required'] = 'Please share your email so we can reply.';
        $page->extra = $extra;
        $page->save();

        $resp = $this->from('/contact')->post('/contact', [
            'name' => '', 'email' => '', 'subject' => '', 'message' => '',
        ]);

        $resp->assertRedirect('/contact');
        $resp->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $errors = session('errors');
        $this->assertSame(
            'Please share your email so we can reply.',
            $errors->first('email')
        );
        // The other three fields fall back to Laravel's default
        // ":attribute is required" wording.
        $this->assertStringContainsString('required', strtolower($errors->first('name')));
        $this->assertStringContainsString('required', strtolower($errors->first('subject')));
        $this->assertStringContainsString('required', strtolower($errors->first('message')));
        // None of them accidentally inherit the email override.
        $this->assertStringNotContainsString('Please share your email', $errors->first('name'));
        $this->assertStringNotContainsString('Please share your email', $errors->first('subject'));
        $this->assertStringNotContainsString('Please share your email', $errors->first('message'));
    }

    public function test_invalid_social_url_is_rejected_by_validation(): void
    {
        // The controller requires social URLs to start with http(s)://
        // — defense in depth so we never persist a `javascript:` URL
        // that the public template would happily render into an
        // anchor's href.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [
                    'twitter'   => 'javascript:alert(1)',
                    'instagram' => '', 'linkedin' => '', 'youtube' => '', 'facebook' => '',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.social.twitter', $errors);
    }

    public function test_public_contact_page_renders_every_saved_extra_value(): void
    {
        // Mirror of AboutSitePageEditorTest's public-render assertion: a
        // future change to resources/views/public/contact.blade.php that
        // accidentally drops a field (e.g. removes the YouTube icon from
        // $socialIcons, or hard-codes the seeded Hyderabad address) would
        // still pass the persistence test above but render the wrong
        // page. This test walks the rendered HTML and checks every
        // saved value actually appears.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $payload = $this->payload([
            'address' => "Sayzio HQ\n42 Example Street\nBengaluru 560001",
            'email'   => 'hello@example.com',
            'phone'   => '+91 40 9876 5432',
            'hours'   => "Mon–Fri · 09:00 – 17:00",
            'social'  => [
                'twitter'   => 'https://twitter.com/onein',
                'instagram' => 'https://instagram.com/onein',
                'linkedin'  => 'https://linkedin.com/company/onein',
                'youtube'   => 'https://youtube.com/@onein',
                'facebook'  => 'https://facebook.com/onein',
            ],
            'map' => [
                'lat'   => 12.9716,
                'lng'   => 77.5946,
                'zoom'  => 12,
                'label' => 'Bengaluru office',
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // Every line of the multi-line address survives nl2br + e().
        $publicResp->assertSee('Sayzio HQ', false);
        $publicResp->assertSee('42 Example Street', false);
        $publicResp->assertSee('Bengaluru 560001', false);

        // Email rendered both as anchor href and as visible text.
        $publicResp->assertSee('mailto:hello@example.com', false);
        $publicResp->assertSee('hello@example.com', false);

        // Phone — visible exactly as entered, plus a tel: href with all
        // non-digit/non-plus characters stripped.
        $publicResp->assertSee('+91 40 9876 5432', false);
        $publicResp->assertSee('tel:+914098765432', false);

        // Hours — both lines of the (admittedly single-line) value.
        $publicResp->assertSee('Mon–Fri · 09:00 – 17:00', false);

        // Every non-empty social URL must reach the rendered page —
        // catches a future regression that drops one platform from the
        // $socialIcons whitelist in the Blade view.
        $publicResp->assertSee('https://twitter.com/onein', false);
        $publicResp->assertSee('https://instagram.com/onein', false);
        $publicResp->assertSee('https://linkedin.com/company/onein', false);
        $publicResp->assertSee('https://youtube.com/@onein', false);
        $publicResp->assertSee('https://facebook.com/onein', false);
        // The corresponding icon titles confirm each anchor is the
        // right platform (catches a copy/paste swap where two keys
        // share an icon).
        $publicResp->assertSee('title="X (Twitter)"', false);
        $publicResp->assertSee('title="Instagram"', false);
        $publicResp->assertSee('title="LinkedIn"', false);
        $publicResp->assertSee('title="YouTube"', false);
        $publicResp->assertSee('title="Facebook"', false);

        // Map: lat/lng/zoom are surfaced via data-* attributes that the
        // Leaflet bootstrap script reads, and the label is rendered
        // both as a data attribute and as visible caption text.
        $publicResp->assertSee('data-lat="12.9716"', false);
        $publicResp->assertSee('data-lng="77.5946"', false);
        $publicResp->assertSee('data-zoom="12"', false);
        $publicResp->assertSee('data-label="Bengaluru office"', false);
        $publicResp->assertSee('Bengaluru office', false); // visible caption + aria-label
        // The "View larger map" link encodes the same coordinates and
        // zoom into the OpenStreetMap permalink — guards against a
        // regression that decouples the link from the map. The view
        // formats coordinates with sprintf('%F', ...) which always
        // pads to 6 decimal places.
        $publicResp->assertSee('mlat=12.971600', false);
        $publicResp->assertSee('mlon=77.594600', false);
        $publicResp->assertSee('#map=12/12.971600/77.594600', false);
    }

    public function test_public_contact_page_omits_anchors_for_blank_social_keys(): void
    {
        // If the admin clears (say) youtube + facebook, the Blade view
        // must skip those <a> elements entirely — never render
        // `<a href="">` placeholders. An empty anchor would still be
        // clickable in the layout's grid and would point at the same
        // page, which is both a UX and a (mild) crawl/SEO hazard.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => 'Some address line',
                'email'   => '',
                'phone'   => '',
                'hours'   => '',
                'social'  => [
                    'twitter'   => 'https://twitter.com/onein',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // The one populated platform still renders.
        $publicResp->assertSee('https://twitter.com/onein', false);
        $publicResp->assertSee('title="X (Twitter)"', false);

        // Each blank platform's icon title must NOT appear — those
        // titles only render inside the `@if($url !== '')` branch, so
        // their absence proves the empty <a> wasn't emitted either.
        $publicResp->assertDontSee('title="Instagram"', false);
        $publicResp->assertDontSee('title="LinkedIn"', false);
        $publicResp->assertDontSee('title="YouTube"', false);
        $publicResp->assertDontSee('title="Facebook"', false);

        // Belt-and-suspenders: assert the rendered HTML contains no
        // empty-href anchor at all. The contact view's other anchors
        // (mailto:, tel:, OSM permalink, form submit URL, layout nav)
        // are always non-empty when populated, so a stray `href=""`
        // would have to come from a regressed social loop.
        $this->assertStringNotContainsString('href=""', $publicResp->getContent());
    }

    public function test_empty_extra_column_falls_back_to_bundled_defaults_on_public_view(): void
    {
        // Set the page's extra column directly to an empty array,
        // mirroring a freshly-installed site that has never visited
        // the editor. The public controller short-circuits to
        // contactExtraDefault() in that case, so the page must render
        // the bundled images and the canonical default copy
        // (heading/labels/feature cards) without crashing.
        $page = $this->makeContactPage();
        $page->forceFill(['extra' => []])->save();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // Default copy from contactExtraDefault() shows up.
        $publicResp->assertSee('Contact', false);                      // hero badge label
        $publicResp->assertSee('Replies within 1 business day', false);// availability text
        $publicResp->assertSee('EN · हिन्दी', false);                  // languages
        $publicResp->assertSee('Friendly humans', false);              // floating card title
        $publicResp->assertSee('Behind every reply', false);           // floating card subtitle
        $publicResp->assertSee('Contact details', false);              // details_heading

        // Bundled image defaults.
        $publicResp->assertSee('/images/marketing/contact/hero.png', false);
        $publicResp->assertSee('/images/marketing/contact/office.png', false);

        // Default feature cards.
        $publicResp->assertSee('Fast replies', false);
        $publicResp->assertSee('Partnerships', false);
        $publicResp->assertSee('Feature ideas', false);

        // Default form copy.
        $publicResp->assertSee('Send us a message', false);            // heading
        $publicResp->assertSee('Your name', false);                    // name label
        $publicResp->assertSee('Email', false);                        // email label
        $publicResp->assertSee('Subject', false);                      // subject label
        $publicResp->assertSee('Message', false);                      // message label
        $publicResp->assertSee('Send message', false);                 // submit label
    }

    public function test_blank_admin_save_keeps_image_and_url_fallbacks_on_public_view(): void
    {
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        // After the admin saves with every editable extra field
        // blanked out, the normalized payload contains empty strings
        // (not nulls), so contact.blade.php's `?? 'literal'` text
        // fallbacks don't fire — but the `$or($field ?? '', …)`
        // image fallbacks must, so the page never renders a broken
        // <img src=""> or a half-empty submit button.
        $payload = [
            'title'            => 'Contact Sayzio',
            'meta_description' => '',
            'sections'         => [
                ['id' => '', 'heading' => 'We love hearing from you', 'body' => 'Drop us a note.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => [
                'hero' => [
                    'badge_label'       => '',
                    'badge_icon'        => '',
                    'availability_text' => '',
                    'availability_icon' => '',
                    'languages'         => '',
                    'side_image'        => '',
                    'side_image_alt'    => '',
                    'floating_card'     => ['title' => '', 'subtitle' => '', 'icon' => ''],
                ],
                'address'         => '',
                'email'           => '',
                'phone'           => '',
                'hours'           => '',
                'social'          => ['twitter' => '', 'instagram' => '', 'linkedin' => '', 'youtube' => '', 'facebook' => ''],
                'map'             => ['lat' => '', 'lng' => '', 'zoom' => '', 'label' => ''],
                'details_heading' => '',
                // Note: omitting `feature_cards` here makes
                // normalizeContactExtra store it as `[]`, which hides
                // the cards on the public view (the admin editor
                // intentionally exposes that as the way to remove the
                // row entirely).
                'office_image'    => ['url' => '', 'alt' => ''],
                'form'            => [
                    'heading'             => '',
                    'intro'               => '',
                    'name_label'          => '',
                    'name_placeholder'    => '',
                    'email_label'         => '',
                    'email_placeholder'   => '',
                    'subject_label'       => '',
                    'subject_placeholder' => '',
                    'message_label'       => '',
                    'message_placeholder' => '',
                    'submit_label'        => '',
                ],
            ],
        ];

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // Image-style fallbacks (use the public view's `$or` helper,
        // which substitutes any falsy value).
        $publicResp->assertSee('/images/marketing/contact/hero.png',   false);
        $publicResp->assertSee('/images/marketing/contact/office.png', false);

        // Submit button literally falls through to its hard-coded
        // template default ("Send message") even when blank.
        $publicResp->assertSee('Send message', false);
    }

    public function test_validation_rejects_image_urls_that_arent_absolute_or_relative(): void
    {
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        // The controller's regex rules require image URLs to start
        // with `/` (relative) or `http(s)://` (absolute). A bare
        // string like 'not-a-url' must be rejected with 422 rather
        // than slipping through and rendering as a broken <img src>.
        $payload = $this->fullPayload();
        $payload['extra']['hero']['side_image']  = 'not-a-url';
        $payload['extra']['office_image']['url'] = 'also-not-a-url';

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/contact', $payload);

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.hero.side_image',   $errors);
        $this->assertArrayHasKey('extra.office_image.url',  $errors);
    }

    public function test_validation_accepts_relative_and_absolute_image_urls(): void
    {
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        // Both forms must round-trip cleanly so the editor doesn't
        // reject perfectly valid URLs (e.g. an admin pasting a CDN
        // URL or a local /storage path).
        $payload = $this->fullPayload();
        $payload['extra']['hero']['side_image']  = 'https://cdn.example.com/hero.png';
        $payload['extra']['office_image']['url'] = '/storage/uploads/office.png';

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();
    }
}
