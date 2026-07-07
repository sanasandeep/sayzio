<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\DialerChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The dialer's one-tap channel buttons (call / SMS / WhatsApp / Telegram /
 * Signal / Viber) are rendered by the shared partial
 * user/dialer/_channel_actions.blade.php, which must only render the channels
 * the user enabled via App\Modules\User\Support\DialerChannels::enabledFor().
 *
 * A disabled channel appearing on ANY surface is a preference-respect bug, so
 * this pins the two web surfaces that include the partial in server-rendered
 * HTML:
 *
 *   (1) the dialer index page (via the favorites strip — favourites, frequent
 *       and recents all include the same partial), plus its @json enabled
 *       payload that drives the keypad's client-rendered channel row, and
 *   (2) the number profile page (/user/dialer/profile?number=…).
 *
 * The partial renders each button as onclick="chanOpen('<js>','<number>')",
 * so asserting on the chanOpen(...) marker with the seeded number targets
 * exactly the partial's output (the page JS only contains template-literal
 * chanOpen calls, never a literal number).
 *
 * Also covered: changing the preference through the real settings endpoint
 * (POST /user/dialer/channels) immediately changes what both pages render —
 * i.e. the per-request memo is forgotten and the stored settings win.
 */
class DialerChannelActionsPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER = '+15550001111';

    private function makeUser(): User
    {
        return User::factory()->create([
            'name'  => 'chan' . Str::random(4),
            'email' => 'chan-' . Str::random(8) . '@example.com',
        ]);
    }

    /**
     * Bind an active workspace in the session — the web dialer routes are
     * gated by `workspace.can:settings.view` (see DialerLiveSyncTest).
     */
    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    /** Seed a favorite so the index page renders the channel-actions partial. */
    private function seedFavorite(User $user): void
    {
        DialerFavorite::create([
            'user_id'     => $user->id,
            'number_e164' => self::NUMBER,
            'label'       => 'Chan Target',
            'sort_order'  => 1,
        ]);
    }

    /** The partial's per-channel button marker for the seeded number. */
    private function marker(string $js): string
    {
        return "chanOpen('{$js}','" . self::NUMBER . "')";
    }

    /**
     * Assert a page renders exactly the given channel `js` modes for the
     * seeded number — every enabled one present, every other one absent.
     *
     * @param  list<string>  $enabledJs
     */
    private function assertRendersOnly($response, array $enabledJs): void
    {
        $allJs = array_map(
            fn (array $meta) => $meta['js'],
            array_values(DialerChannels::catalog()),
        );
        foreach ($allJs as $js) {
            // The quotes around the js mode + number are literal template
            // text (only the values pass through {{ }}), so match unescaped.
            if (in_array($js, $enabledJs, true)) {
                $response->assertSee($this->marker($js), false);
            } else {
                $response->assertDontSee($this->marker($js), false);
            }
        }
    }

    public function test_index_renders_default_channels_only(): void
    {
        $user = $this->makeUser();
        $this->seedFavorite($user);

        $response = $this->actingAsWeb($user)->get(route('user.dialer.index'));
        $response->assertOk();

        // Defaults: call, sms, whatsapp, telegram — signal & viber stay off.
        $this->assertRendersOnly($response, ['tel', 'sms', 'wa', 'tg']);

        // The keypad's client-rendered row is driven by the same enabled list.
        $response->assertSee('["call","sms","whatsapp","telegram"]', false);
    }

    public function test_index_renders_only_user_enabled_channels(): void
    {
        $user = $this->makeUser();
        $user->settings = ['dialer_channels' => ['signal', 'call']];
        $user->save();
        $this->seedFavorite($user);

        $response = $this->actingAsWeb($user)->get(route('user.dialer.index'));
        $response->assertOk();

        // Only the picked channels render — WhatsApp/Telegram/SMS/Viber must
        // NOT appear anywhere the partial renders the seeded number.
        $this->assertRendersOnly($response, ['signal', 'tel']);

        // Preference order is preserved in the enabled payload.
        $response->assertSee('["signal","call"]', false);
    }

    public function test_profile_page_renders_only_user_enabled_channels(): void
    {
        $user = $this->makeUser();
        $user->settings = ['dialer_channels' => ['viber', 'sms']];
        $user->save();

        $response = $this->actingAsWeb($user)->get(
            route('user.dialer.profile', ['number' => self::NUMBER]),
        );
        $response->assertOk();

        $this->assertRendersOnly($response, ['viber', 'sms']);
    }

    public function test_profile_page_renders_defaults_when_no_preference_stored(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAsWeb($user)->get(
            route('user.dialer.profile', ['number' => self::NUMBER]),
        );
        $response->assertOk();

        $this->assertRendersOnly($response, ['tel', 'sms', 'wa', 'tg']);
    }

    public function test_settings_change_updates_both_pages_immediately(): void
    {
        $user = $this->makeUser();
        $this->seedFavorite($user);

        // Starts on the defaults.
        $this->actingAsWeb($user);
        $this->assertRendersOnly(
            $this->get(route('user.dialer.index'))->assertOk(),
            ['tel', 'sms', 'wa', 'tg'],
        );

        // Change the preference through the real settings endpoint.
        $save = $this->post(route('user.dialer.channels'), [
            'channels' => ['telegram', 'viber'],
        ]);
        $save->assertOk();
        $save->assertJsonPath('data.enabled', ['telegram', 'viber']);

        // Both surfaces immediately reflect the new selection — nothing
        // disabled may linger, nothing enabled may be missing.
        $this->assertRendersOnly(
            $this->get(route('user.dialer.index'))->assertOk(),
            ['tg', 'viber'],
        );
        $this->assertRendersOnly(
            $this->get(route('user.dialer.profile', ['number' => self::NUMBER]))->assertOk(),
            ['tg', 'viber'],
        );

        // Garbage keys sanitize back to the defaults (never zero channels).
        $this->post(route('user.dialer.channels'), ['channels' => ['bogus']])
            ->assertOk()
            ->assertJsonPath('data.enabled', DialerChannels::defaults());
        $this->assertRendersOnly(
            $this->get(route('user.dialer.profile', ['number' => self::NUMBER]))->assertOk(),
            ['tel', 'sms', 'wa', 'tg'],
        );
    }
}
