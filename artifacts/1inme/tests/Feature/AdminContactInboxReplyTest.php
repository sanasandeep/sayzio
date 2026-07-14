<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\ContactMessageReply;
use App\Modules\Common\Services\Emailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin Contact Inbox — reply feature tests.
 *
 * Covers:
 *  - Successful send-and-persist: email logged, reply row created, status → replied.
 *  - Permission gate: a guest cannot submit a reply.
 *  - No-email messages: reply route returns an error, no reply stored.
 *  - Validation: empty body and empty subject are rejected.
 */
class AdminContactInboxReplyTest extends TestCase
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
        ]);
    }

    private function makeMessage(array $attrs = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'name'    => 'Visitor User',
            'email'   => 'visitor@example.com',
            'subject' => 'Question about pricing',
            'message' => 'Hi, I have a question about your pricing.',
            'status'  => 'new',
        ], $attrs));
    }

    public function test_admin_can_reply_and_reply_is_persisted(): void
    {
        $admin   = $this->makeAdmin();
        $message = $this->makeMessage();

        $emailLog = null;

        // Intercept the Emailer send and capture what it stores, without
        // requiring a live SMTP connection (the 'log' transport writes to log).
        // The reply row and message status are the primary assertions per
        // the email-test conventions (assert in-app state, not mail counts).

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contact-inbox.reply', $message), [
                'reply_subject' => 'Re: Question about pricing',
                'reply_body'    => 'Thanks for reaching out! Happy to help.',
            ])
            ->assertRedirect();

        // Reply row persisted.
        $this->assertDatabaseHas('contact_message_replies', [
            'contact_message_id' => $message->id,
            'admin_id'           => $admin->id,
            'subject'            => 'Re: Question about pricing',
            'body'               => 'Thanks for reaching out! Happy to help.',
        ]);

        // Message status updated to 'replied' and replied_at stamped.
        $message->refresh();
        $this->assertSame('replied', $message->status);
        $this->assertNotNull($message->replied_at);

        // email_logs row written (send or failed — either proves the pipeline ran).
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'contact.inbox_reply',
            'recipient' => 'visitor@example.com',
        ]);
    }

    public function test_guest_cannot_send_a_reply(): void
    {
        $message = $this->makeMessage();

        $this->post(route('admin.contact-inbox.reply', $message), [
            'reply_subject' => 'Re: Hello',
            'reply_body'    => 'Some reply.',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('contact_message_replies', [
            'contact_message_id' => $message->id,
        ]);
    }

    public function test_reply_to_message_without_email_returns_error(): void
    {
        $admin   = $this->makeAdmin();
        $message = $this->makeMessage([
            'email'         => '',
            'contact_phone' => '+1234567890',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contact-inbox.reply', $message), [
                'reply_subject' => 'Re: hello',
                'reply_body'    => 'Some reply.',
            ])
            ->assertSessionHasErrors('reply');

        $this->assertDatabaseMissing('contact_message_replies', [
            'contact_message_id' => $message->id,
        ]);

        // Status must not change.
        $this->assertSame('new', $message->fresh()->status);
    }

    public function test_validation_rejects_empty_body(): void
    {
        $admin   = $this->makeAdmin();
        $message = $this->makeMessage();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contact-inbox.reply', $message), [
                'reply_subject' => 'Re: hi',
                'reply_body'    => '',
            ])
            ->assertSessionHasErrors('reply_body');

        $this->assertDatabaseMissing('contact_message_replies', [
            'contact_message_id' => $message->id,
        ]);
    }

    public function test_validation_rejects_empty_subject(): void
    {
        $admin   = $this->makeAdmin();
        $message = $this->makeMessage();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contact-inbox.reply', $message), [
                'reply_subject' => '',
                'reply_body'    => 'Some reply.',
            ])
            ->assertSessionHasErrors('reply_subject');

        $this->assertDatabaseMissing('contact_message_replies', [
            'contact_message_id' => $message->id,
        ]);
    }

    public function test_index_shows_replied_status_tab(): void
    {
        $admin   = $this->makeAdmin();
        $message = $this->makeMessage(['status' => 'replied', 'replied_at' => now()]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contact-inbox.index', ['status' => 'replied']))
            ->assertOk()
            ->assertSee('Question about pricing')
            ->assertSee('Replied');
    }
}
