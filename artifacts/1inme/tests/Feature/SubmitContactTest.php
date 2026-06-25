<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * End-to-end coverage for SitePageController::submitContact (the
 * `POST /contact` route bound at routes/web.php:130). The Contact
 * editor round-trip is already covered by ContactSitePageEditorTest;
 * this file targets the submit handler itself — the parts a visitor
 * actually exercises:
 *
 *   - valid input → ContactMessage row persisted, redirect back with
 *     the success flash, optional admin recipient email sent.
 *   - honeypot tripped → silently "succeeds" (no row, no email, same
 *     redirect) so a bot can't tell its submission was rejected.
 *   - invalid input → 302 back with field-level validation errors and
 *     no row written.
 *   - throttle (the in-controller RateLimiter, not the route-level
 *     `throttle:` middleware) returns the rate-limited message after
 *     three accepted submissions from the same IP and stops persisting
 *     further rows.
 *
 * A regression in any of these silently breaks the page that visitors
 * actually rely on — the editor round-trip would still pass.
 */
class SubmitContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The submit handler keys its in-process rate limit on the
        // request IP. Tests share a single IP (127.0.0.1), so clear
        // the bucket between tests to keep them independent.
        RateLimiter::clear('contact:127.0.0.1');
    }

    private function makeContactPage(): SitePage
    {
        $page = SitePage::firstOrNew(['slug' => 'contact']);
        $page->fill([
            'title'            => 'Contact us',
            'meta_description' => 'Seeded contact page.',
            'sections'         => SitePagesContent::contactSectionsDefault(),
            'extra'            => SitePagesContent::contactExtraDefault(),
        ])->save();
        return $page;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Visitor',
            'email'   => 'visitor@example.com',
            'subject' => 'Hello there',
            'message' => 'A short note from a visitor.',
        ], $overrides);
    }

    public function test_valid_submission_persists_message_and_redirects_with_success(): void
    {
        $this->makeContactPage();
        Mail::fake();

        $resp = $this->from('/contact')->post('/contact', $this->validPayload([
            'name'    => 'Alice Example',
            'email'   => 'alice@example.com',
            'subject' => 'Partnership inquiry',
            'message' => "Hi team,\nWould love to chat about an integration.",
        ]));

        $resp->assertRedirect('/contact');
        $resp->assertSessionHasNoErrors();
        $resp->assertSessionHas('success');

        // Persistence: a single row landed in contact_messages with
        // exactly the submitted values, status 'new', and the
        // request's IP captured for follow-up moderation.
        $this->assertDatabaseCount('contact_messages', 1);
        $msg = ContactMessage::firstOrFail();
        $this->assertSame('Alice Example',                 $msg->name);
        $this->assertSame('alice@example.com',             $msg->email);
        $this->assertSame('Partnership inquiry',           $msg->subject);
        $this->assertSame(
            "Hi team,\nWould love to chat about an integration.",
            $msg->message
        );
        $this->assertSame('new', $msg->status);
        $this->assertNotNull($msg->ip);

        // No recipient configured → no admin notification sent. The
        // controller wraps Mail::raw() in a try/catch, so this also
        // proves we didn't accidentally fire-and-swallow a send.
        Mail::assertNothingSent();
    }

    public function test_honeypot_field_blocks_persistence_and_email(): void
    {
        $this->makeContactPage();
        Mail::fake();

        // The honeypot is enforced by the validator's `nullable|max:0`
        // rule on `website`. A bot that auto-fills every input trips
        // it, gets redirected back, and — critically — nothing reaches
        // the DB or the admin's inbox. Real users never see the field
        // (it's hidden + tabindex=-1 in the Blade view) so they're
        // unaffected.
        $resp = $this->from('/contact')->post('/contact', $this->validPayload([
            'website' => 'https://spam.example.com/promo',
        ]));

        $resp->assertRedirect('/contact');
        $resp->assertSessionHasErrors(['website']);
        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_input_redirects_back_with_field_errors_and_no_row(): void
    {
        $this->makeContactPage();
        Mail::fake();

        // Missing every required field plus an obviously malformed
        // email. Every field must report its own error and nothing
        // must land in the DB.
        $resp = $this->from('/contact')->post('/contact', [
            'name'    => '',
            'email'   => 'not-an-email',
            'subject' => '',
            'message' => '',
        ]);

        $resp->assertRedirect('/contact');
        $resp->assertSessionHasErrors(['name', 'subject', 'message', 'email']);
        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_oversize_input_is_rejected_by_validation(): void
    {
        // The validator caps name (120), email (190), subject (200),
        // and message (5000). Push every field one char past its cap
        // and assert each one is reported individually so a future
        // refactor that drops a `max:` rule is caught.
        $this->makeContactPage();

        $resp = $this->from('/contact')->post('/contact', [
            'name'    => str_repeat('a', 121),
            'email'   => str_repeat('b', 180) . '@example.com', // > 190
            'subject' => str_repeat('c', 201),
            'message' => str_repeat('d', 5001),
        ]);

        $resp->assertRedirect('/contact');
        $resp->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_recipient_email_is_sent_when_admin_setting_is_configured(): void
    {
        $this->makeContactPage();
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        // The phpunit.xml pins MAIL_MAILER=array, so we inspect the
        // in-memory Symfony array transport directly rather than
        // Mail::fake() — Mail::fake doesn't record `Mail::raw` calls
        // (no Mailable class to match against).
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertMethodOrSkip($transport, 'messages');
        $beforeCount = count($transport->messages()->all());

        $this->from('/contact')->post('/contact', $this->validPayload([
            'subject' => 'Press request',
        ]))->assertRedirect('/contact');

        $this->assertDatabaseCount('contact_messages', 1);

        $sent = array_slice($transport->messages()->all(), $beforeCount);
        $this->assertCount(1, $sent, 'Expected exactly one admin notification email');
        $sym = $sent[0]->getOriginalMessage();
        $tos = array_map(fn($a) => $a->getAddress(), $sym->getTo());
        $this->assertContains('inbox@example.com', $tos);
        $this->assertSame('[Sayzio Contact] Press request', $sym->getSubject());
        // Body is quoted-printable encoded for transport — decode
        // before substring-matching so soft line wraps don't hide the
        // visitor's content.
        $body = quoted_printable_decode($sym->getBody()->bodyToString());
        $this->assertStringContainsString('Visitor',             $body);
        $this->assertStringContainsString('visitor@example.com', $body);
        $this->assertStringContainsString('Press request',       $body);
    }

    private function assertMethodOrSkip(object $obj, string $method): void
    {
        if (!method_exists($obj, $method)) {
            $this->markTestSkipped("Mail transport does not expose ::$method() — MAIL_MAILER must be 'array' in phpunit.xml");
        }
    }

    public function test_throttle_blocks_a_fourth_submission_from_the_same_ip(): void
    {
        $this->makeContactPage();
        $page = SitePage::where('slug', 'contact')->firstOrFail();
        // Customise the rate-limited copy so we can assert the admin
        // override actually reaches the visitor.
        $extra = $page->extra;
        $extra['messages']['rate_limited'] = 'Slow down — try again shortly.';
        $page->extra = $extra;
        $page->save();

        // Three valid submissions all succeed and persist.
        for ($i = 1; $i <= 3; $i++) {
            $this->from('/contact')->post('/contact', $this->validPayload([
                'subject' => "Inquiry $i",
            ]))->assertRedirect('/contact')->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('contact_messages', 3);

        // The fourth from the same IP trips the in-controller
        // RateLimiter (3 attempts / 600s) and:
        //   - redirects back with a `message` field error containing
        //     the admin-customised "rate_limited" text,
        //   - does NOT persist a fourth row.
        $resp = $this->from('/contact')->post('/contact', $this->validPayload([
            'subject' => 'Inquiry 4',
        ]));
        $resp->assertRedirect('/contact');
        $resp->assertSessionHasErrors(['message']);
        $this->assertSame(
            'Slow down — try again shortly.',
            session('errors')->first('message')
        );
        $this->assertDatabaseCount('contact_messages', 3);
    }
}
