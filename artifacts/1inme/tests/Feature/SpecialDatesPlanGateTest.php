<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\SpecialDateWishLog;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\SpecialDates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6649 — coverage for the `special_dates` plan gate (Task #6646).
 *
 * Legacy-safe default is ON: plans without the key (and users without a
 * plan) keep the feature. Only a plan that stores `special_dates=false`
 * turns it off, which must:
 *  - strip `special_dates` from the creator-profile save path (entries are
 *    neither created nor cleared);
 *  - make the wish command skip the gated creator entirely (no follower
 *    notifications, no heads-up, no wish-log claim) while still fanning
 *    out for allowed creators in the same run.
 */
class SpecialDatesPlanGateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(array $features): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function makeUser(?Plan $plan = null, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'handle'  => 'sdgate' . uniqid(),
            'plan_id' => $plan?->id,
        ], $attrs));
    }

    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $ws;
    }

    private function saveDates(User $user, array $dates): \Illuminate\Testing\TestResponse
    {
        $this->bind($user);

        return $this->actingAs($user)->post(route('user.creator-profile.update'), [
            'special_dates' => $dates,
        ]);
    }

    private const BIRTHDAY_INPUT = [
        ['kind' => 'birthday', 'date' => '1990-03-12', 'public' => '1', 'notify' => '1', 'sync' => '0'],
    ];

    // ── Save path ──────────────────────────────────────────────────────────

    public function test_gated_plan_save_does_not_persist_entries(): void
    {
        $plan = $this->plan(['special_dates' => false]);
        $user = $this->makeUser($plan);

        $this->saveDates($user, self::BIRTHDAY_INPUT)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame([], SpecialDates::entries($user->refresh()));
    }

    public function test_gated_plan_save_does_not_clear_preexisting_entries(): void
    {
        // A creator downgraded onto a gated plan keeps stored entries — the
        // gate strips the field entirely, it never rewrites what's there.
        $entry = [
            'id' => 'kept-' . uniqid(), 'kind' => 'birthday', 'label' => '',
            'date' => '1990-03-12', 'public' => true, 'notify' => true,
            'sync' => false, 'calendar_event_id' => null,
        ];
        $plan = $this->plan(['special_dates' => false]);
        $user = $this->makeUser($plan);
        $user->forceFill(['special_dates' => [$entry]])->save();

        // Both an explicit list and the empty "clear" marker must be ignored.
        $this->saveDates($user, [
            ['kind' => 'anniversary', 'date' => '2015-09-20', 'public' => '1'],
        ])->assertRedirect();
        $this->assertSame([$entry['id']], array_column(SpecialDates::entries($user->refresh()), 'id'));

        $this->bind($user);
        $this->actingAs($user)->post(route('user.creator-profile.update'), [
            'special_dates' => '',
        ])->assertRedirect();
        $this->assertSame([$entry['id']], array_column(SpecialDates::entries($user->refresh()), 'id'));
    }

    public function test_plan_without_key_still_saves_legacy_default_on(): void
    {
        $plan = $this->plan(['max_links' => 100]); // no special_dates key
        $user = $this->makeUser($plan);

        $this->saveDates($user, self::BIRTHDAY_INPUT)->assertRedirect();

        $entries = SpecialDates::entries($user->refresh());
        $this->assertCount(1, $entries);
        $this->assertSame('birthday', $entries[0]['kind']);
    }

    public function test_planless_user_still_saves(): void
    {
        $user = $this->makeUser(null);

        $this->saveDates($user, self::BIRTHDAY_INPUT)->assertRedirect();

        $this->assertCount(1, SpecialDates::entries($user->refresh()));
    }

    public function test_plan_with_key_true_saves(): void
    {
        $plan = $this->plan(['special_dates' => true]);
        $user = $this->makeUser($plan);

        $this->saveDates($user, self::BIRTHDAY_INPUT)->assertRedirect();

        $this->assertCount(1, SpecialDates::entries($user->refresh()));
    }

    // ── Editor render (Task #6651) ─────────────────────────────────────────

    private function editor(User $user): \Illuminate\Testing\TestResponse
    {
        $this->bind($user);

        return $this->actingAs($user)->get(route('user.creator-profile.edit'));
    }

    public function test_gated_plan_editor_hides_repeater_and_shows_upgrade_hint(): void
    {
        $user = $this->makeUser($this->plan(['special_dates' => false]));

        $this->editor($user)->assertOk()
            ->assertSee('special-dates-upgrade-hint')
            ->assertSee('Upgrade plan')
            ->assertDontSee('Add a special date');
    }

    public function test_plan_without_key_editor_still_shows_repeater(): void
    {
        $user = $this->makeUser($this->plan(['max_links' => 100]));

        $this->editor($user)->assertOk()
            ->assertSee('Add a special date')
            ->assertDontSee('special-dates-upgrade-hint');
    }

    public function test_planless_user_editor_still_shows_repeater(): void
    {
        $this->editor($this->makeUser(null))->assertOk()
            ->assertSee('Add a special date')
            ->assertDontSee('special-dates-upgrade-hint');
    }

    // ── Wish command ───────────────────────────────────────────────────────

    /** A creator with today's notify-enabled birthday and one follower. */
    private function seedWishCreator(?Plan $plan, string $entryId): array
    {
        $creator  = $this->makeUser($plan, ['timezone' => 'UTC']);
        $follower = $this->makeUser(null, ['handle' => null]);

        $creator->forceFill(['special_dates' => [[
            'id' => $entryId, 'kind' => 'birthday', 'label' => '',
            'date' => '1990-03-12', 'public' => true, 'notify' => true,
            'sync' => false, 'calendar_event_id' => null,
        ]]])->save();

        Follow::create(['follower_id' => $follower->id, 'creator_id' => $creator->id, 'created_at' => now()]);

        return [$creator, $follower];
    }

    public function test_wish_command_skips_gated_creator_and_fans_out_for_allowed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-12 09:30:00', 'UTC'));

        $gatedPlan  = $this->plan(['special_dates' => false]);
        $legacyPlan = $this->plan(['max_links' => 100]); // no key → default ON

        [$gated, $gatedFollower]   = $this->seedWishCreator($gatedPlan, 'gated-entry');
        [$legacy, $legacyFollower] = $this->seedWishCreator($legacyPlan, 'legacy-entry');

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        // Gated creator: nothing at all — no follower note, no heads-up,
        // not even an occurrence claim (a later upgrade can still send).
        $this->assertSame(0, UserNotification::where('user_id', $gatedFollower->id)->where('type', 'special_date_wish')->count());
        $this->assertSame(0, UserNotification::where('user_id', $gated->id)->where('type', 'special_date_wish')->count());
        $this->assertSame(0, SpecialDateWishLog::where('user_id', $gated->id)->count());

        // Legacy creator in the SAME run still fans out normally.
        $this->assertSame(1, UserNotification::where('user_id', $legacyFollower->id)->where('type', 'special_date_wish')->count());
        $this->assertSame(1, UserNotification::where('user_id', $legacy->id)->where('type', 'special_date_wish')->count());
        $this->assertSame(1, SpecialDateWishLog::where('user_id', $legacy->id)->where('entry_id', 'legacy-entry')->count());
    }
}
