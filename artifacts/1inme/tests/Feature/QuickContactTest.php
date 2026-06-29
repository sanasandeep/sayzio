<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\QuickContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the multi-channel quick-contact path that real leads
 * flow through:
 *
 *   - {@see QuickContactService::validate()} per channel — the
 *     callback (Indian-only), WhatsApp (country-coded) and email
 *     channels each normalise/reject their contact value. A bad
 *     normalisation here silently drops or mis-routes a real lead, so
 *     each channel is pinned for both an accepted and a rejected value.
 *   - {@see QuickContactService::create()} — persists the chosen
 *     channel + phone onto the ContactMessage and notifies the admin
 *     inbox by email (an email_logs row through the central Emailer).
 *   - the anonymous, throttled POST /assistant/quick-contact route —
 *     a visitor who isn't signed in must still land in the admin
 *     Contact Inbox AND trigger the admin email.
 *
 * Email is asserted through the email_logs row the Emailer writes
 * (MAIL_MAILER=array in phpunit.xml, so the send never actually goes
 * out but is fully recorded) rather than the Mail facade — the Emailer
 * uses Mail::raw()/html() which Mail::fake() does not record.
 */
class QuickContactTest extends TestCase
{
    use RefreshDatabase;

    // ---- validate(): callback (Indian phone only) -------------------

    public function test_validate_callback_normalizes_valid_indian_numbers(): void
    {
        $cases = [
            '9876543210'        => '+919876543210', // bare 10-digit
            '+91 98765 43210'   => '+919876543210', // +91 with spaces
            '09876543210'       => '+919876543210', // trunk 0 prefix
            '91-98765-43210'    => '+919876543210', // 91 country code, dashes
            '(987) 654-3210'    => '+919876543210', // punctuation
        ];

        foreach ($cases as $input => $expected) {
            $phone = $input;
            $error = QuickContactService::validate('callback', $phone, null);
            $this->assertNull($error, "Expected '$input' to be a valid Indian number");
            $this->assertSame($expected, $phone, "Expected '$input' to normalise to $expected");
        }
    }

    public function test_validate_callback_rejects_non_indian_or_malformed_numbers(): void
    {
        $bad = [
            '1234567890',       // starts with 1 (not 6-9)
            '5876543210',       // starts with 5
            '98765',            // too short
            '987654321098765',  // too long
            '+1 555 123 4567',  // US number
            'not-a-number',     // no digits
        ];

        foreach ($bad as $input) {
            $phone = $input;
            $error = QuickContactService::validate('callback', $phone, null);
            $this->assertNotNull($error, "Expected '$input' to be rejected for callback");
            $this->assertStringContainsString('Indian', $error);
        }
    }

    public function test_validate_callback_requires_a_phone(): void
    {
        $phone = '   ';
        $error = QuickContactService::validate('callback', $phone, null);
        $this->assertNotNull($error);
        $this->assertStringContainsString('call back', $error);
    }

    // ---- validate(): whatsapp (must carry a country code) -----------

    public function test_validate_whatsapp_normalizes_country_coded_numbers(): void
    {
        $cases = [
            '+1 555 123 4567'   => '+15551234567',  // explicit +, US
            '+91 98765 43210'   => '+919876543210', // explicit +, India
            '15551234567'       => '+15551234567',  // no +, >=11 digits
            '+44 20 7946 0958'  => '+442079460958', // UK
        ];

        foreach ($cases as $input => $expected) {
            $phone = $input;
            $error = QuickContactService::validate('whatsapp', $phone, null);
            $this->assertNull($error, "Expected '$input' to be a valid WhatsApp number");
            $this->assertSame($expected, $phone, "Expected '$input' to normalise to $expected");
        }
    }

    public function test_validate_whatsapp_rejects_numbers_without_a_country_code(): void
    {
        $bad = [
            '9876543210',   // 10 digits, no + → ambiguous, rejected
            '12345',        // too short
            '+12',          // below 8-digit floor
            '+1234567890123456', // above 15-digit ceiling
            'no-digits',    // empty after stripping
        ];

        foreach ($bad as $input) {
            $phone = $input;
            $error = QuickContactService::validate('whatsapp', $phone, null);
            $this->assertNotNull($error, "Expected '$input' to be rejected for whatsapp");
            $this->assertStringContainsString('country code', $error);
        }
    }

    // ---- validate(): email -----------------------------------------

    public function test_validate_email_accepts_a_valid_address_and_clears_phone(): void
    {
        $phone = '9876543210'; // should be nulled out for the email channel
        $error = QuickContactService::validate('email', $phone, 'lead@example.com');
        $this->assertNull($error);
        $this->assertNull($phone, 'Phone must be cleared on the email channel');
    }

    public function test_validate_email_rejects_a_malformed_address(): void
    {
        $phone = null;
        $error = QuickContactService::validate('email', $phone, 'not-an-email');
        $this->assertNotNull($error);
        $this->assertStringContainsString('valid email', $error);
    }

    public function test_validate_rejects_an_unknown_channel(): void
    {
        $phone = '9876543210';
        $error = QuickContactService::validate('carrier-pigeon', $phone, null);
        $this->assertNotNull($error);
    }

    // ---- create(): persistence + admin email -----------------------

    public function test_create_persists_channel_and_phone_and_emails_admin(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        $contact = QuickContactService::create([
            'name'    => 'Lead Person',
            'email'   => 'lead@example.com',
            'subject' => 'Quick contact: Call back (phone)',
            'message' => 'Please call me back.',
            'channel' => 'callback',
            'phone'   => '+919876543210',
            'ip'      => '203.0.113.7',
        ]);

        // The channel + normalised phone are persisted for the inbox.
        $this->assertDatabaseCount('contact_messages', 1);
        $fresh = ContactMessage::firstOrFail();
        $this->assertSame('callback',       $fresh->contact_channel);
        $this->assertSame('+919876543210',  $fresh->contact_phone);
        $this->assertSame('Lead Person',    $fresh->name);
        $this->assertSame('new',            $fresh->status);
        $this->assertSame('203.0.113.7',    $fresh->ip);
        $this->assertSame($contact->id,     $fresh->id);

        // The admin notification was sent through the central Emailer,
        // which records an email_logs row (status sent under the array
        // transport) keyed by the registry entry + the configured
        // recipient.
        $log = EmailLog::where('email_key', 'support.contact_request')->first();
        $this->assertNotNull($log, 'Expected an admin notification email to be logged');
        $this->assertSame('inbox@example.com', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString('+919876543210', (string) $log->body);
        $this->assertStringContainsString('Call back (phone)', (string) $log->body);
    }

    public function test_create_email_channel_records_email_as_the_reach(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        QuickContactService::create([
            'name'    => 'Email Lead',
            'email'   => 'reach@example.com',
            'subject' => 'Quick contact: Email',
            'message' => 'Reach me by email.',
            'channel' => 'email',
            'phone'   => null,
            'ip'      => '203.0.113.8',
        ]);

        $fresh = ContactMessage::firstOrFail();
        $this->assertSame('email', $fresh->contact_channel);
        $this->assertNull($fresh->contact_phone);

        $log = EmailLog::where('email_key', 'support.contact_request')->firstOrFail();
        $this->assertStringContainsString('reach@example.com', (string) $log->body);
        $this->assertStringContainsString('Email', (string) $log->body);
    }

    // ---- POST /assistant/quick-contact (anonymous, throttled) ------

    public function test_anonymous_quick_contact_route_persists_row_and_emails_admin(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        $resp = $this->postJson('/assistant/quick-contact', [
            'name'    => 'Anon Visitor',
            'channel' => 'callback',
            'phone'   => '+91 98765 43210',
            'message' => 'Call me when you can.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        // Landed in the admin Contact Inbox with the normalised phone +
        // channel — the lead is reachable even though they never signed in.
        $this->assertDatabaseCount('contact_messages', 1);
        $row = ContactMessage::firstOrFail();
        $this->assertSame('callback',       $row->contact_channel);
        $this->assertSame('+919876543210',  $row->contact_phone);
        $this->assertSame('Anon Visitor',   $row->name);

        // And the admin email fired.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'support.contact_request',
            'recipient' => 'inbox@example.com',
            'status'    => 'sent',
        ]);
    }

    public function test_anonymous_quick_contact_route_rejects_a_bad_phone_without_persisting(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        // A US number on the Indian-only callback channel must be
        // rejected with a 422 and leave nothing behind — neither an
        // inbox row nor an admin email.
        $resp = $this->postJson('/assistant/quick-contact', [
            'name'    => 'Bad Lead',
            'channel' => 'callback',
            'phone'   => '+1 555 123 4567',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['ok' => false]);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'support.contact_request',
        ]);
    }

    public function test_anonymous_quick_contact_route_accepts_the_whatsapp_channel(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        $resp = $this->postJson('/assistant/quick-contact', [
            'name'    => 'WhatsApp Lead',
            'channel' => 'whatsapp',
            'phone'   => '+1 555 123 4567',
            'message' => 'Ping me on WhatsApp.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        $this->assertDatabaseCount('contact_messages', 1);
        $row = ContactMessage::firstOrFail();
        $this->assertSame('whatsapp',      $row->contact_channel);
        $this->assertSame('+15551234567',  $row->contact_phone);
        $this->assertSame('WhatsApp Lead', $row->name);

        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'support.contact_request',
            'recipient' => 'inbox@example.com',
            'status'    => 'sent',
        ]);
    }

    public function test_anonymous_quick_contact_route_accepts_the_email_channel(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        $resp = $this->postJson('/assistant/quick-contact', [
            'name'    => 'Email Lead',
            'channel' => 'email',
            'email'   => 'reach@example.com',
            'phone'   => '9876543210', // ignored / cleared on the email channel
            'message' => 'Email me back.',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        // Landed in the admin Contact Inbox on the email channel with the
        // phone cleared — the email itself is the reach.
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

    public function test_anonymous_quick_contact_route_rejects_an_invalid_email_without_persisting(): void
    {
        AppSetting::put('contact_recipient_email', 'inbox@example.com');

        // The email channel demands a syntactically valid address. Laravel's
        // request validation (`email` rule) rejects a malformed address with
        // a 422 before the controller body runs, so nothing is persisted and
        // no admin email is sent.
        $resp = $this->postJson('/assistant/quick-contact', [
            'name'    => 'Bad Email Lead',
            'channel' => 'email',
            'email'   => 'not-an-email',
        ]);

        $resp->assertStatus(422);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseMissing('email_logs', [
            'email_key' => 'support.contact_request',
        ]);
    }

    public function test_anonymous_quick_contact_route_rejects_a_missing_channel(): void
    {
        // The channel is required; omitting it is a 422 with nothing left
        // behind — a malformed widget submission can't create an empty lead.
        $resp = $this->postJson('/assistant/quick-contact', [
            'name' => 'No Channel Lead',
        ]);

        $resp->assertStatus(422);
        $this->assertDatabaseCount('contact_messages', 0);
    }
}
