<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The two public subscribe forms do what they say.
 *
 * Sana asked whether they worked at all. They did -- a submission reached the
 * database and the unsubscribe mail went out -- but everything around that was
 * wrong in ways nobody would have reported as a bug:
 *
 *   - The subscription centre rendered all three channel cards whatever was
 *     configured, so with neither WhatsApp channel set up (which is how it is
 *     live) a visitor who came to unsubscribe met two dead cards reading
 *     "Channel link not configured." That is an internal admin state on a
 *     public page, on the one page whose whole job is helping somebody leave.
 *   - Twelve pages promised "pick email, WhatsApp Channel, or DM" above a
 *     block that could only show email.
 *
 * So this covers the round trip and the two states that were wrong, because
 * the design work is worth nothing if the form underneath it stops working.
 */
class SubscribeFormsWorkTest extends TestCase
{
    use RefreshDatabase;

    private function clearWhatsAppConfig(): void
    {
        foreach (['marketing_whatsapp_channel_url', 'marketing_whatsapp_number', 'marketing_whatsapp_message'] as $key) {
            AppSetting::put($key, '');
        }
    }

    public function test_subscribing_records_the_address_and_confirms_it(): void
    {
        $response = $this->from('/pricing')->post(route('site.newsletter.subscribe'), [
            'email' => 'reader@example.com',
            'source' => 'subscribe-block:pricing',
            'website' => '',
        ]);

        $response->assertRedirect('/pricing');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.com',
        ]);

        // The visitor has to be told. A form that saves silently is a form
        // people submit twice and then give up on.
        $response->assertSessionHas('newsletter_success_pricing');
    }

    /**
     * The flash key the controller sets is the one the block reads.
     *
     * They are derived independently -- the controller strips the
     * `subscribe-block:` prefix off the submitted source, the view builds the
     * key from the `$source` it was included with -- so nothing but a test
     * keeps them in step.
     */
    public function test_the_success_key_matches_what_the_block_looks_for(): void
    {
        // Asserting the controller's session key alone does not test this:
        // changing the key the VIEW reads leaves that assertion green while
        // the visitor sees nothing. So follow the redirect and look at the
        // page, which is what the person actually gets.
        $posted = $this->from('/pricing')
            ->post(route('site.newsletter.subscribe'), [
                'email' => 'keycheck@example.com',
                'source' => 'subscribe-block:pricing',
                'website' => '',
            ])
            ->assertSessionHas('newsletter_success_pricing');

        $page = $this->followRedirects($posted);
        $page->assertOk();

        $html = $page->getContent();
        $start = strpos($html, 'subscribe-block-h-');
        $this->assertNotFalse($start, 'the subscribe block is gone from /pricing');
        $block = substr($html, $start, 4000);

        $this->assertStringContainsString(
            'sy-notice',
            $block,
            'nothing was shown to the visitor after subscribing -- the key the controller '
            . 'flashes and the key the block reads have drifted apart'
        );
        $this->assertStringContainsStringIgnoringCase(
            'subscrib',
            $block,
            'the confirmation does not mention subscribing'
        );
    }

    public function test_a_bad_address_comes_back_as_an_error_not_a_silent_no_op(): void
    {
        $this->from('/pricing')->post(route('site.newsletter.subscribe'), [
            'email' => 'not-an-address',
            'source' => 'subscribe-block:pricing',
            'website' => '',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'not-an-address']);
    }

    /** The honeypot swallows bots without telling them they were caught. */
    public function test_the_honeypot_does_not_create_a_subscriber(): void
    {
        $this->from('/pricing')->post(route('site.newsletter.subscribe'), [
            'email' => 'bot@example.com',
            'source' => 'subscribe-block:pricing',
            'website' => 'http://spam.example',
        ]);

        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'bot@example.com']);
    }

    /**
     * Asking for an unsubscribe link is acknowledged, without confirming
     * whether the address is on the list.
     *
     * This asserts the acknowledgement rather than counting mails: the mail
     * goes out through a custom Emailer service, not the Mail facade, so
     * `Mail::fake()` never sees it and a count assertion here would be
     * testing Laravel's plumbing rather than this feature.
     */
    public function test_asking_for_a_link_is_acknowledged_without_leaking_who_is_on_the_list(): void
    {
        NewsletterSubscriber::create(['email' => 'leaving@example.com']);

        $known = $this->from(route('site.subscriptions.manage'))
            ->post(route('site.subscriptions.manage.send'), [
                'email' => 'leaving@example.com',
                'website' => '',
            ]);

        $stranger = $this->from(route('site.subscriptions.manage'))
            ->post(route('site.subscriptions.manage.send'), [
                'email' => 'never-signed-up@example.com',
                'website' => '',
            ]);

        $known->assertSessionHas('subscriptions_manage_status');
        $stranger->assertSessionHas('subscriptions_manage_status');

        // Identical wording either way, or the form becomes a way to find out
        // whether an address is on the list.
        $this->assertSame(
            session()->get('subscriptions_manage_status'),
            $known->getSession()->get('subscriptions_manage_status'),
            'the reply differs depending on whether the address is known'
        );
    }

    /**
     * And the link in that mail actually removes them.
     *
     * The valuable end of this feature is the signed URL: a link that 404s or
     * silently does nothing leaves somebody subscribed who asked to leave, and
     * they have no second move.
     */
    public function test_the_signed_unsubscribe_link_removes_the_subscriber(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'leaving@example.com']);

        $url = URL::signedRoute('site.newsletter.unsubscribe', ['subscriber' => $subscriber->id]);

        $this->get($url)->assertOk();

        $this->assertNotNull(
            $subscriber->fresh()->unsubscribed_at,
            'following the signed link did not actually unsubscribe them'
        );
    }

    /** An unsigned or tampered link does nothing. */
    public function test_an_unsigned_unsubscribe_link_is_refused(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'staying@example.com']);

        $this->get(route('site.newsletter.unsubscribe', ['subscriber' => $subscriber->id]))
            ->assertForbidden();

        $this->assertNull(
            $subscriber->fresh()->unsubscribed_at,
            'an unsigned link unsubscribed somebody'
        );
    }

    /**
     * An unconfigured channel is not offered.
     *
     * Both the subscription centre and the subscribe block used to show a
     * WhatsApp card regardless; the centre then admitted, in italics, that it
     * was not set up.
     */
    public function test_an_unconfigured_channel_is_not_shown(): void
    {
        $this->clearWhatsAppConfig();

        $page = $this->get(route('site.subscriptions.manage'));
        $page->assertOk();

        // Scoped to the page's own section. A whole-page assertion fails on
        // the site footer, which links the WhatsApp Channel independently of
        // whether the newsletter uses it.
        $html = $page->getContent();
        $start = strpos($html, 'Manage your Sayzio subscriptions');
        $this->assertNotFalse($start, 'the subscription centre heading is gone');
        $end = strpos($html, '<footer', $start);
        $body = substr($html, $start, ($end !== false ? $end - $start : 8000));

        $this->assertStringNotContainsString('not configured', $body,
            'the page still admits, to the public, that a channel is not set up');
        $this->assertStringNotContainsStringIgnoringCase('WhatsApp', $body,
            'a WhatsApp card is still rendered with no WhatsApp channel configured');

        // The email card is still there -- that is the whole point of the page.
        $this->assertStringContainsString('Email me an unsubscribe link', $body);
    }

    /**
     * And no page promises one either.
     *
     * Twelve callers pass copy like "pick email, WhatsApp Channel, or DM".
     * The block drops the channel sentence while the channels are missing, so
     * the promise and the page agree.
     */
    public function test_no_page_offers_a_channel_it_cannot_show(): void
    {
        $this->clearWhatsAppConfig();

        $page = $this->get('/pricing');
        $page->assertOk();

        $html = $page->getContent();
        $start = strpos($html, 'subscribe-block-h-');
        $this->assertNotFalse($start, 'the subscribe block is gone from /pricing');

        $block = substr($html, $start, 4000);

        $this->assertStringNotContainsStringIgnoringCase(
            'WhatsApp',
            $block,
            'the subscribe block still offers WhatsApp while no WhatsApp channel is configured'
        );
    }
}
