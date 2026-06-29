<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum-surface coverage for the mobile "Contact us" flow, which posts
 * to POST /api/v1/assistant/quick-contact — the mobile mirror of the web
 * standalone quick-contact widget. Both share the SAME contract +
 * QuickContactService (see QuickContactTest for the service unit + web
 * route coverage); this suite pins the /api/v1 route itself so a mobile
 * request can never silently fail:
 *
 *   - each channel (callback / whatsapp / email) lands a ContactMessage in
 *     the admin Contact Inbox with the right contact_channel + contact_phone,
 *   - the admin notification email actually fires (email_logs row),
 *   - a malformed contact value returns 422 with a readable, visitor-facing
 *     message AND leaves nothing behind (no inbox row, no admin email).
 *
 * The route is anonymous (api.optional_auth), so these post without a token
 * exactly like the always-anonymous mobile widget. Validation errors that
 * trip Laravel's request rules (e.g. a malformed email) surface through the
 * standardized /api/* envelope { error: { message, code, details } }, while
 * channel-specific rejections from QuickContactService return
 * { ok: false, error: "<message>" } — both readable by the mobile client.
 *
 * Email is asserted via the email_logs row the central Emailer writes
 * (MAIL_MAILER=array in phpunit.xml), not the Mail facade — the Emailer
 * uses Mail::raw()/html() which Mail::fake() does not record.
 */
class QuickContactApiTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/v1/assistant/quick-contact';

    private function setRecipient(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');
    }

    // ---- happy path: each channel reaches the inbox + emails admin -----

    public function test_api_callback_channel_lands_in_inbox_with_normalized_phone(): void
    {
        $this->setRecipient();

        $resp = $this->postJson(self::ROUTE, [
            'name'    => 'Mobile Lead',
            'channel' => 'callback',
            'phone'   => '+91 98765 43210',
            'message' => 'Please call me back.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        // The success copy is the readable confirmation the mobile screen shows.
        $this->assertNotEmpty($resp->json('message'));

        $this->assertDatabaseCount('contact_messages', 1);
        $row = ContactMessage::firstOrFail();
        $this->assertSame('callback',      $row->contact_channel);
        $this->assertSame('+919876543210', $row->contact_phone);
        $this->assertSame('Mobile Lead',   $row->name);
        $this->assertSame('new',           $row->status);

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'support.contact_request',
            'recipient' => 'inbox@example.com',
            'status'    => 'sent',
        ]);
    }

    public function test_api_whatsapp_channel_lands_in_inbox_with_country_coded_phone(): void
    {
        $this->setRecipient();

        $resp = $this->postJson(self::ROUTE, [
            'name'    => 'WhatsApp Lead',
            'channel' => 'whatsapp',
            'phone'   => '+1 555 123 4567',
            'message' => 'Ping me on WhatsApp.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        $this->assertDatabaseCount('contact_messages', 1);
        $row = ContactMessage::firstOrFail();
        $this->assertSame('whatsapp',     $row->contact_channel);
        $this->assertSame('+15551234567', $row->contact_phone);
        $this->assertSame('WhatsApp Lead', $row->name);

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'support.contact_request',
            'recipient' => 'inbox@example.com',
            'status'    => 'sent',
        ]);
    }

    public function test_api_email_channel_lands_in_inbox_with_phone_cleared(): void
    {
        $this->setRecipient();

        $resp = $this->postJson(self::ROUTE, [
            'name'    => 'Email Lead',
            'channel' => 'email',
            'email'   => 'reach@example.com',
            'phone'   => '9876543210', // cleared on the email channel
            'message' => 'Email me back.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        $this->assertDatabaseCount('contact_messages', 1);
        $row = ContactMessage::firstOrFail();
        $this->assertSame('email',             $row->contact_channel);
        $this->assertNull($row->contact_phone);
        $this->assertSame('reach@example.com', $row->email);

        $log = EmailLog::where('email_key', 'support.contact_request')->firstOrFail();
        $this->assertSame('inbox@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString('reach@example.com', (string) $log->body);
    }

    // ---- failure path: invalid input surfaces a readable 422 -----------

    public function test_api_callback_rejects_a_non_indian_number_with_a_readable_422(): void
    {
        $this->setRecipient();

        // A US number on the Indian-only callback channel is rejected by
        // QuickContactService::validate() with a visitor-facing message.
        $resp = $this->postJson(self::ROUTE, [
            'name'    => 'Bad Lead',
            'channel' => 'callback',
            'phone'   => '+1 555 123 4567',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['ok' => false]);
        // The error must be a readable string the mobile screen can show
        // inline (apiFetch lifts `body.error` into ApiError.message).
        $error = $resp->json('error');
        $this->assertIsString($error);
        $this->assertStringContainsString('Indian', $error);

        // Nothing silently persisted, no admin email.
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'support.contact_request',
        ]);
    }

    public function test_api_whatsapp_rejects_a_number_without_a_country_code_with_a_readable_422(): void
    {
        $this->setRecipient();

        $resp = $this->postJson(self::ROUTE, [
            'channel' => 'whatsapp',
            'phone'   => '9876543210', // ambiguous, no country code
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['ok' => false]);
        $error = $resp->json('error');
        $this->assertIsString($error);
        $this->assertStringContainsString('country code', $error);

        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'support.contact_request',
        ]);
    }

    public function test_api_email_rejects_a_malformed_address_with_a_readable_422(): void
    {
        $this->setRecipient();

        // Laravel's `email` request rule rejects this before the controller
        // body runs; the /api/* exception handler maps it to the standard
        // envelope { error: { message, code: validation_failed, details } }.
        $resp = $this->postJson(self::ROUTE, [
            'channel' => 'email',
            'email'   => 'not-an-email',
        ]);

        $resp->assertStatus(422);
        $message = $resp->json('error.message');
        $this->assertIsString($message);
        $this->assertNotEmpty($message);
        $this->assertSame('validation_failed', $resp->json('error.code'));

        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'support.contact_request',
        ]);
    }

    public function test_api_rejects_a_missing_channel_with_a_422(): void
    {
        // The channel is required; a malformed submission can't create an
        // empty lead.
        $resp = $this->postJson(self::ROUTE, [
            'name' => 'No Channel Lead',
        ]);

        $resp->assertStatus(422);
        $this->assertSame('validation_failed', $resp->json('error.code'));
        $this->assertDatabaseCount('contact_messages', 0);
    }
}
