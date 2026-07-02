<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the QR Studio deep-link prefill: per-link "QR Code" actions across
 * the app now open the advanced builder (user.qr-codes.create) pre-bound to
 * that link via ?link_id=N instead of the legacy simple per-link QR page.
 *
 *  - An owned link_id seeds the existing-link picker (useExistingLink=true,
 *    linkId=N) and prefills the name as "QR — {title|alias}".
 *  - A link_id owned by someone else is ignored (no IDOR / no prefill).
 *  - An owned but inactive link (outside the recent-200 active picker query)
 *    is injected into the picker so the selection is representable.
 *  - Plain create (no link_id) is unaffected.
 */
class QrStudioLinkPrefillTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'qp' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeLink(User $user, array $attrs = []): Link
    {
        return Link::create($attrs + [
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Page ' . Str::random(4),
            'is_active' => true,
        ]);
    }

    public function test_owned_link_id_prefills_builder(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner);

        $resp = $this->actingAs($owner)->get('/user/qr-codes/create?link_id=' . $link->id);

        $resp->assertOk();
        $resp->assertSee('useExistingLink: true', false);
        $resp->assertSee('linkId: ' . $link->id, false);
        $resp->assertSee('QR — ' . $link->title, false);
    }

    public function test_foreign_link_id_is_ignored(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $theirs = $this->makeLink($other, ['title' => 'Secret Foreign Page']);

        $resp = $this->actingAs($owner)->get('/user/qr-codes/create?link_id=' . $theirs->id);

        $resp->assertOk();
        $resp->assertSee('useExistingLink: false', false);
        $resp->assertSee('linkId: null', false);
        $resp->assertDontSee('Secret Foreign Page');
    }

    public function test_inactive_owned_link_is_injected_into_picker(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, ['is_active' => false, 'title' => 'Paused Page']);

        $resp = $this->actingAs($owner)->get('/user/qr-codes/create?link_id=' . $link->id);

        $resp->assertOk();
        $resp->assertSee('linkId: ' . $link->id, false);
        // The inactive link falls outside the active-links picker query but
        // must still appear as a selectable <option>.
        $resp->assertSee('value="' . $link->id . '"', false);
        $resp->assertSee('Paused Page');
    }

    public function test_plain_create_is_unaffected(): void
    {
        $owner = $this->makeUser();

        $resp = $this->actingAs($owner)->get('/user/qr-codes/create');

        $resp->assertOk();
        $resp->assertSee('useExistingLink: false', false);
        $resp->assertSee('linkId: null', false);
    }
}
