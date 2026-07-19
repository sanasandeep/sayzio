<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\CheckClickMilestonesJob;
use App\Jobs\PersistLinkClicksJob;
use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LinkWebhookTriggersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        // Bind the workspace context so `workspace.can:*` gated web routes
        // and workspace_owner_id() resolve to this user.
        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($this->user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $this->user);
    }

    // ─── helpers ────────────────────────────────────────────────────────

    private function grantWebhookFeature(): void
    {
        // Assign a plan that has the webhook_triggers feature enabled.
        $slug = 'plan-' . strtolower(str()->random(6));
        $plan = \App\Modules\Admin\Models\Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['webhook_triggers' => true],
        ]);
        $this->user->plan_id = $plan->id;
        $this->user->save();
        $this->user->refresh();
    }

    private function createDestination(array $overrides = []): InboxForwardDestination
    {
        return InboxForwardDestination::create(array_merge([
            'user_id'   => $this->user->id,
            'label'     => 'Test webhook',
            'type'      => 'webhook',
            'target'    => 'https://example.com/hook',
            'method'    => 'POST',
            'sources'   => null,
            'is_active' => true,
        ], $overrides));
    }

    private function createLink(array $overrides = []): Link
    {
        return Link::create(array_merge([
            'user_id'      => $this->user->id,
            'alias'        => 'test-' . uniqid(),
            'type'         => 'short',
            'title'        => 'Test link',
            'destination'  => 'https://example.com',
            'total_clicks' => 0,
        ], $overrides));
    }

    // ─── InboxAggregator source constants ───────────────────────────────

    public function test_aggregator_exposes_link_event_source_constants(): void
    {
        $this->assertSame('link_created',     InboxAggregator::SOURCE_LINK_CREATED);
        $this->assertSame('link_expired',     InboxAggregator::SOURCE_LINK_EXPIRED);
        $this->assertSame('click_milestone',  InboxAggregator::SOURCE_CLICK_MILESTONE);
    }

    public function test_aggregator_link_event_labels_includes_all_three(): void
    {
        $labels = InboxAggregator::linkEventLabels();
        $this->assertArrayHasKey('link_created',    $labels);
        $this->assertArrayHasKey('link_expired',     $labels);
        $this->assertArrayHasKey('click_milestone',  $labels);
    }

    public function test_aggregator_source_labels_includes_link_events(): void
    {
        $all = InboxAggregator::sourceLabels();
        $this->assertArrayHasKey('link_created',   $all);
        $this->assertArrayHasKey('link_expired',    $all);
        $this->assertArrayHasKey('click_milestone', $all);
    }

    public function test_inbox_sources_does_not_include_link_events(): void
    {
        $inbox = InboxAggregator::inboxSources();
        $this->assertNotContains('link_created',   $inbox);
        $this->assertNotContains('link_expired',    $inbox);
        $this->assertNotContains('click_milestone', $inbox);
    }

    // ─── InboxForwardDestination model ──────────────────────────────────

    public function test_click_milestone_thresholds_empty_when_null(): void
    {
        $dest = $this->createDestination(['click_milestones' => null]);
        $this->assertSame([], $dest->clickMilestoneThresholds());
    }

    public function test_click_milestone_thresholds_returns_sorted_unique_positives(): void
    {
        $dest = $this->createDestination(['click_milestones' => [1000, 100, 100, 0, -5, 5000]]);
        $this->assertSame([100, 1000, 5000], $dest->clickMilestoneThresholds());
    }

    // ─── Plan gate ──────────────────────────────────────────────────────

    public function test_dispatch_for_link_created_skips_when_feature_off(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $dest = $this->createDestination(['sources' => ['link_created']]);
        $link = $this->createLink();

        app(InboxForwarder::class)->dispatchForLinkCreated($this->user->id, $link);

        Http::assertNothingSent();
    }

    public function test_dispatch_for_link_expired_skips_when_feature_off(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $dest = $this->createDestination();
        $link = $this->createLink();

        app(InboxForwarder::class)->dispatchForLinkExpired($this->user->id, $link);

        Http::assertNothingSent();
    }

    // ─── Dispatch – link_created ─────────────────────────────────────────

    public function test_dispatch_for_link_created_sends_webhook_when_feature_on(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createDestination(['sources' => null]); // all sources
        $link = $this->createLink();

        app(InboxForwarder::class)->dispatchForLinkCreated($this->user->id, $link);

        Http::assertSent(fn ($r) =>
            str_contains($r->url(), 'example.com/hook')
            && json_decode($r->body(), true)['event'] === 'link_created'
            && json_decode($r->body(), true)['link']['id'] === $link->id
        );
    }

    public function test_dispatch_for_link_created_respects_source_filter(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['*' => Http::response('OK', 200)]);

        // Destination only subscribed to link_expired, NOT link_created
        $dest = $this->createDestination(['sources' => ['link_expired']]);
        $link = $this->createLink();

        app(InboxForwarder::class)->dispatchForLinkCreated($this->user->id, $link);

        Http::assertNothingSent();
    }

    // ─── Dispatch – link_expired ─────────────────────────────────────────

    public function test_dispatch_for_link_expired_sends_webhook_with_reason(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createDestination(['sources' => null]);
        $link = $this->createLink(['expires_at' => now()->subHour()]);

        app(InboxForwarder::class)->dispatchForLinkExpired($this->user->id, $link);

        Http::assertSent(function ($r) use ($link) {
            $b = json_decode($r->body(), true);
            return $b['event'] === 'link_expired'
                && $b['link']['id'] === $link->id
                && isset($b['reason']);
        });
    }

    // ─── Dispatch – click_milestone ─────────────────────────────────────

    public function test_dispatch_for_click_milestone_sends_webhook(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createDestination([
            'sources'          => ['click_milestone'],
            'click_milestones' => [100],
        ]);
        $link = $this->createLink(['total_clicks' => 100]);

        app(InboxForwarder::class)->dispatchForClickMilestone(
            $this->user->id, $link, 100, 100, $dest
        );

        Http::assertSent(function ($r) use ($link) {
            $b = json_decode($r->body(), true);
            return $b['event'] === 'click_milestone'
                && $b['milestone'] === 100
                && $b['total_clicks'] === 100
                && $b['link']['id'] === $link->id;
        });
    }

    public function test_dispatch_for_click_milestone_skips_when_feature_off(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);
        $dest = $this->createDestination(['click_milestones' => [100]]);
        $link = $this->createLink(['total_clicks' => 100]);

        app(InboxForwarder::class)->dispatchForClickMilestone(
            $this->user->id, $link, 100, 100, $dest
        );

        Http::assertNothingSent();
    }

    // ─── Bearer auth header + delivery row shape ────────────────────────

    private function createBearerDestination(array $overrides = []): InboxForwardDestination
    {
        return $this->createDestination(array_merge([
            'header_key'   => 'Authorization',
            'header_value' => 'Bearer secret-token-123',
        ], $overrides));
    }

    public function test_link_created_webhook_carries_bearer_auth_header_and_queues_delivery(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createBearerDestination(['sources' => ['link_created']]);
        $link = $this->createLink();

        app(InboxForwarder::class)->dispatchForLinkCreated($this->user->id, $link);

        Http::assertSent(function ($r) use ($link) {
            $b = json_decode($r->body(), true);
            return $r->hasHeader('Authorization', 'Bearer secret-token-123')
                && $r->hasHeader('X-Sayzio-Event', 'link_created')
                && $b['event'] === 'link_created'
                && isset($b['occurred_at'])
                && $b['link']['id'] === $link->id
                && $b['link']['alias'] === $link->alias
                && $b['link']['type'] === $link->type
                && array_key_exists('title', $b['link']);
        });

        $delivery = \App\Modules\User\Models\InboxForwardDelivery::where('destination_id', $dest->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('link_created', $delivery->source_type);
        $this->assertSame($link->id, (int) $delivery->source_id);
        $this->assertSame('success', $delivery->status);
        $this->assertSame('link_created', $delivery->payload_snapshot['event'] ?? null);
        $this->assertSame($link->id, $delivery->payload_snapshot['link']['id'] ?? null);
    }

    public function test_link_expired_webhook_carries_bearer_auth_header_and_queues_delivery(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createBearerDestination(['sources' => ['link_expired']]);
        $link = $this->createLink(['expires_at' => now()->subHour()]);

        app(InboxForwarder::class)->dispatchForLinkExpired($this->user->id, $link);

        Http::assertSent(function ($r) use ($link) {
            $b = json_decode($r->body(), true);
            return $r->hasHeader('Authorization', 'Bearer secret-token-123')
                && $r->hasHeader('X-Sayzio-Event', 'link_expired')
                && $b['event'] === 'link_expired'
                && $b['reason'] === 'date'
                && isset($b['occurred_at'])
                && $b['link']['id'] === $link->id
                && array_key_exists('expires_at', $b['link'])
                && array_key_exists('total_clicks', $b['link']);
        });

        $delivery = \App\Modules\User\Models\InboxForwardDelivery::where('destination_id', $dest->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('link_expired', $delivery->source_type);
        $this->assertSame('success', $delivery->status);
        $this->assertSame('link_expired', $delivery->payload_snapshot['event'] ?? null);
    }

    public function test_click_milestone_webhook_carries_bearer_auth_header_and_queues_delivery(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $dest = $this->createBearerDestination([
            'sources'          => ['click_milestone'],
            'click_milestones' => [500],
        ]);
        $link = $this->createLink(['total_clicks' => 512]);

        app(InboxForwarder::class)->dispatchForClickMilestone(
            $this->user->id, $link, 500, 512, $dest
        );

        Http::assertSent(function ($r) use ($link) {
            $b = json_decode($r->body(), true);
            return $r->hasHeader('Authorization', 'Bearer secret-token-123')
                && $r->hasHeader('X-Sayzio-Event', 'click_milestone')
                && $b['event'] === 'click_milestone'
                && $b['milestone'] === 500
                && $b['total_clicks'] === 512
                && isset($b['occurred_at'])
                && $b['link']['id'] === $link->id;
        });

        $delivery = \App\Modules\User\Models\InboxForwardDelivery::where('destination_id', $dest->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('click_milestone', $delivery->source_type);
        $this->assertSame($link->id, (int) $delivery->source_id);
        $this->assertSame('success', $delivery->status);
        $this->assertSame(500, $delivery->payload_snapshot['milestone'] ?? null);
    }

    public function test_plan_gate_creates_no_delivery_rows_when_feature_off(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);
        $dest = $this->createBearerDestination(['click_milestones' => [100]]);
        $link = $this->createLink(['total_clicks' => 100]);

        $forwarder = app(InboxForwarder::class);
        $forwarder->dispatchForLinkCreated($this->user->id, $link);
        $forwarder->dispatchForLinkExpired($this->user->id, $link);
        $forwarder->dispatchForClickMilestone($this->user->id, $link, 100, 100, $dest);

        Http::assertNothingSent();
        $this->assertSame(
            0,
            \App\Modules\User\Models\InboxForwardDelivery::where('user_id', $this->user->id)->count()
        );
    }

    // ─── CheckClickMilestonesJob idempotency ────────────────────────────

    public function test_milestone_job_does_not_double_fire_same_threshold(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $link = $this->createLink(['total_clicks' => 500]);
        $dest = $this->createDestination([
            'sources'          => null,
            'click_milestones' => [100],
        ]);

        if (!\Schema::hasTable('link_click_milestone_fires')) {
            $this->markTestSkipped('link_click_milestone_fires table not migrated yet.');
        }

        $job = new CheckClickMilestonesJob([$link->id]);
        $job->handle();

        // First run fires once
        Http::assertSentCount(1);

        // Second run: idempotency guard prevents double-fire
        $job2 = new CheckClickMilestonesJob([$link->id]);
        $job2->handle();

        Http::assertSentCount(1); // still 1
    }

    public function test_milestone_job_fires_per_threshold(): void
    {
        $this->grantWebhookFeature();
        Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

        $link = $this->createLink(['total_clicks' => 1000]);
        $dest = $this->createDestination([
            'sources'          => null,
            'click_milestones' => [100, 500, 1000],
        ]);

        if (!\Schema::hasTable('link_click_milestone_fires')) {
            $this->markTestSkipped('link_click_milestone_fires table not migrated yet.');
        }

        (new CheckClickMilestonesJob([$link->id]))->handle();

        Http::assertSentCount(3); // one per threshold
    }

    // ─── Web controller – validation ────────────────────────────────────

    public function test_web_create_destination_stores_click_milestones(): void
    {
        $this->actingAs($this->user)
             ->post(route('user.inbox.forwards.store'), [
                 'label'              => 'Milestone hook',
                 'type'               => 'webhook',
                 'target'             => 'https://example.com/hook',
                 'click_milestones'   => [100, 1000],
                 'is_active'          => '1',
             ])
             ->assertRedirect();

        $dest = InboxForwardDestination::where('user_id', $this->user->id)->latest()->first();
        $this->assertNotNull($dest);
        $this->assertSame([100, 1000], $dest->clickMilestoneThresholds());
    }

    public function test_web_create_destination_normalises_milestones(): void
    {
        $this->actingAs($this->user)
             ->post(route('user.inbox.forwards.store'), [
                 'label'              => 'Deduped milestones',
                 'type'               => 'webhook',
                 'target'             => 'https://example.com/hook',
                 // Note: 0 / negatives are rejected by validation (min:1),
                 // so only duplicates + ordering are normalised here.
                 'click_milestones'   => [1000, 100, 100],
                 'is_active'          => '1',
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect(route('user.inbox.forwards.index'));

        $dest = InboxForwardDestination::where('user_id', $this->user->id)->latest()->first();
        $this->assertNotNull($dest);
        $this->assertSame([100, 1000], $dest->clickMilestoneThresholds());
    }

    // ─── REST API – validation ───────────────────────────────────────────

    public function test_api_create_destination_includes_click_milestones_in_response(): void
    {
        $resp = $this->withToken($this->token)
                     ->postJson('/api/v1/inbox/forwards', [
                         'label'             => 'API hook',
                         'type'              => 'webhook',
                         'target'            => 'https://example.com/hook',
                         'click_milestones'  => [100, 1000],
                         'is_active'         => true,
                     ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.destination.click_milestones', [100, 1000]);
    }

    public function test_api_create_rejects_invalid_milestone_values(): void
    {
        $resp = $this->withToken($this->token)
                     ->postJson('/api/v1/inbox/forwards', [
                         'label'             => 'Bad milestone',
                         'type'              => 'webhook',
                         'target'            => 'https://example.com/hook',
                         'click_milestones'  => [-5, 0, 'abc'],
                         'is_active'         => true,
                     ]);

        $resp->assertStatus(422);
        // API errors use the unified envelope: {error:{message,code,details}}.
        $resp->assertJsonPath('error.code', 'validation_failed');
        $details = $resp->json('error.details');
        $this->assertArrayHasKey('click_milestones.0', $details);
        $this->assertArrayHasKey('click_milestones.1', $details);
        $this->assertArrayHasKey('click_milestones.2', $details);
    }

    // ─── Webhook settings page ───────────────────────────────────────────

    public function test_webhooks_settings_page_requires_auth(): void
    {
        $this->get(route('user.settings.webhooks.index'))->assertRedirect();
    }

    public function test_webhooks_settings_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
             ->get(route('user.settings.webhooks.index'))
             ->assertOk()
             ->assertViewHas('hasFeature');
    }

    public function test_webhooks_settings_page_shows_upgrade_prompt_without_feature(): void
    {
        $this->actingAs($this->user)
             ->get(route('user.settings.webhooks.index'))
             ->assertOk()
             ->assertSee('Upgrade to unlock');
    }

    public function test_webhooks_settings_page_shows_destinations_with_feature(): void
    {
        $this->grantWebhookFeature();
        $dest = $this->createDestination(['label' => 'My webhook']);

        $this->actingAs($this->user)
             ->get(route('user.settings.webhooks.index'))
             ->assertOk()
             ->assertSee('My webhook')
             ->assertDontSee('Upgrade to unlock');
    }

    // ─── PersistLinkClicksJob queues milestone check ─────────────────────

    public function test_persist_clicks_job_dispatches_milestone_check_for_human_clicks(): void
    {
        Queue::fake();

        $link = $this->createLink();

        // Build a minimal click payload
        $payload = [
            'link_id'       => $link->id,
            'clicked_at'    => now()->toIso8601String(),
            'ip_address'    => '1.2.3.4',
            'is_bot'        => false,
            'referrer'      => null,
            'utm_params'    => null,
        ];

        $job = new PersistLinkClicksJob([$payload]);
        app()->call([$job, 'handle']);

        Queue::assertPushed(CheckClickMilestonesJob::class, function ($job) use ($link) {
            return in_array($link->id, $job->linkIds, true);
        });
    }

    // ─── Schedule is registered ──────────────────────────────────────────

    public function test_check_link_expiry_schedule_entry_exists(): void
    {
        $registries = config('schedules');
        if (empty($registries)) {
            $files = glob(base_path('routes/schedules/*.php'));
            $all = [];
            foreach ($files as $f) {
                $all = array_merge($all, require $f);
            }
        } else {
            $all = $registries;
        }

        $keys = array_column($all, 'key');
        $this->assertContains('webhooks:check-link-expiry', $keys);
    }

    // ─── PremiumFeatures catalogue ───────────────────────────────────────

    public function test_premium_features_catalogue_includes_webhook_triggers(): void
    {
        $catalogue = \App\Modules\Common\Support\PremiumFeatures::catalogue();
        $keys = array_column($catalogue, 'key');
        $this->assertContains('webhook_triggers', $keys);
    }
}
