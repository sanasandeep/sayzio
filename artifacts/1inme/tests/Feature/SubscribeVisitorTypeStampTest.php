<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audience-prompt Alpine component writes the visitor's self-identified
 * persona into a plain (client-side) `ap_type_{link_id}` cookie before they
 * subscribe. RedirectController::subscribe() must stamp that persona onto
 * the new subscriber row — which requires the cookie to survive the
 * EncryptCookies middleware (it is set via document.cookie, so it can never
 * carry Laravel's encryption envelope).
 */
class SubscribeVisitorTypeStampTest extends TestCase
{
    use RefreshDatabase;

    private function makeBiolinkWithEmailBlock(): array
    {
        $user = User::factory()->create();
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'Bio',
            'is_active' => true,
        ]);
        $block = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'email_subscribe',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => [],
        ]);

        // Public routes carry no workspace binding; drop any leaked one so
        // resolveByAlias behaves like a real visitor request.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return [$link, $block];
    }

    public function test_plain_persona_cookie_is_stamped_onto_subscriber(): void
    {
        [$link, $block] = $this->makeBiolinkWithEmailBlock();

        $resp = $this->withCredentials()->withUnencryptedCookie('ap_type_' . $link->id, 'creator')
            ->postJson("/{$link->alias}/subscribe", [
                'block_id' => $block->id,
                'type'     => 'email',
                'email'    => 'fan@example.com',
            ]);

        $resp->assertOk()->assertJson(['success' => true]);

        $sub = Subscriber::where('link_id', $link->id)->where('email', 'fan@example.com')->first();
        $this->assertNotNull($sub);
        $this->assertSame('creator', $sub->visitor_type);
    }

    public function test_invalid_cookie_value_leaves_visitor_type_null(): void
    {
        [$link, $block] = $this->makeBiolinkWithEmailBlock();

        $resp = $this->withCredentials()->withUnencryptedCookie('ap_type_' . $link->id, 'alien<script>')
            ->postJson("/{$link->alias}/subscribe", [
                'block_id' => $block->id,
                'type'     => 'email',
                'email'    => 'fan2@example.com',
            ]);

        $resp->assertOk();

        $sub = Subscriber::where('link_id', $link->id)->where('email', 'fan2@example.com')->first();
        $this->assertNotNull($sub);
        $this->assertNull($sub->visitor_type);
    }

    public function test_missing_cookie_leaves_visitor_type_null(): void
    {
        [$link, $block] = $this->makeBiolinkWithEmailBlock();

        $resp = $this->postJson("/{$link->alias}/subscribe", [
            'block_id' => $block->id,
            'type'     => 'email',
            'email'    => 'fan3@example.com',
        ]);

        $resp->assertOk();

        $sub = Subscriber::where('link_id', $link->id)->where('email', 'fan3@example.com')->first();
        $this->assertNotNull($sub);
        $this->assertNull($sub->visitor_type);
    }
}
