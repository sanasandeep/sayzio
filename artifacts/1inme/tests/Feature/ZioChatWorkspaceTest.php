<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\Voice\VoiceToolRegistry;
use App\Services\AI\ZioPageKnowledge;
use App\Services\AI\ZioTranscriptRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZioChatWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(User $user, string $token): SiteAssistantConversation
    {
        return SiteAssistantConversation::create(['visitor_token' => $token, 'user_id' => $user->id, 'bound_user_id' => $user->id, 'surface' => 'app']);
    }

    private function message(SiteAssistantConversation $conversation, string $text, string $at): void
    {
        $message = new SiteAssistantMessage(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $text]);
        $message->created_at = $at;
        $message->updated_at = $at;
        $message->save();
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/assistant/export?from=2026-10-14&to=2026-10-15')->assertUnauthorized();
    }

    public function test_export_is_owner_scoped_and_respects_offset_date_time_boundaries(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->conversation($actor, 'sa_export_owner');
        $foreign = $this->conversation($other, 'sa_export_foreign');
        $this->message($own, 'Include this reply', '2026-10-14 04:45:00');
        $this->message($own, 'Outside range', '2026-10-14 04:00:00');
        $this->message($foreign, 'Other user private reply', '2026-10-14 04:45:00');
        $anonymous = SiteAssistantConversation::create(['visitor_token' => 'sa_export_anon', 'user_id' => $actor->id, 'surface' => 'marketing']);
        $this->message($anonymous, 'Anonymous conversation', '2026-10-14 04:45:00');
        $response = $this->actingAs($actor)->get('/assistant/export?'.http_build_query(['from' => '2026-10-14T10:00:00+05:30', 'to' => '2026-10-14T10:30:00+05:30']));
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Include this reply', $content);
        foreach (['Outside range', 'Other user private reply', 'Anonymous conversation'] as $text) {
            $this->assertStringNotContainsString($text, $content);
        }
    }

    public function test_export_rejects_reversed_or_invalid_ranges(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/assistant/export?from=2026-10-15&to=2026-10-14')->assertUnprocessable();
    }

    public function test_transcript_recorder_refuses_another_users_token_and_records_voice_cost_without_charging(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversation($actor, 'sa_record_owner');
        ZioTranscriptRecorder::record($other, $conversation->visitor_token, [['role' => 'assistant', 'content' => 'Wrong actor', 'credits_spent' => 9]]);
        $this->assertSame(0, $conversation->messages()->count());
        ZioTranscriptRecorder::record($actor, $conversation->visitor_token, [['role' => 'user', 'content' => 'Voice prompt'], ['role' => 'assistant', 'content' => 'Voice reply', 'credits_spent' => 3, 'meta' => ['source' => 'voice']]]);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(3, $conversation->fresh()->credits_spent);
    }

    public function test_guidance_cannot_call_an_alias_a_social_block(): void
    {
        $r = app(VoiceToolRegistry::class)->execute(User::factory()->create(), false, 'guide_control', ['label' => '+ Add alias', 'instruction' => 'Click to add your social media links.']);
        $this->assertArrayHasKey('error', $r);
        $this->assertArrayNotHasKey('client_action', $r);
    }

    public function test_social_schema_comes_from_native_defaults_and_supported_platforms(): void
    {
        $schema = ZioPageKnowledge::blockSchema('socials');
        $this->assertSame('socials', $schema['data']['type']);
        $this->assertContains('linkedin', $schema['data']['platforms']);
        $this->assertIsArray($schema['data']['defaults']);
        $this->assertArrayHasKey('error', ZioPageKnowledge::blockSchema('invented-type'));
    }

    public function test_native_social_creation_is_saved_and_recorded_only_in_the_actors_chat(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $workspace = app(WorkspaceContext::class)->resolve($actor);
        app()->instance('current_workspace', $workspace);
        $link = Link::create(['user_id' => $actor->id, 'workspace_id' => $workspace->id, 'type' => 'biolink', 'alias' => 'zio-native-social', 'title' => 'My page', 'is_active' => true]);
        $conversation = $this->conversation($actor, 'sa_native_social');
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson(route('user.links.blocks.store', $link), [
            'type' => 'socials', 'settings' => ['platforms' => [['name' => 'instagram', 'url' => 'https://instagram.com/sayzioapp', 'display' => 'icon', 'connection_id' => '']]],
            'is_active' => true, 'assistant_visitor_token' => $conversation->visitor_token,
        ]);
        $this->assertSame(200, $response->status(), (string) $response->headers->get('Location'));
        $response->assertJsonPath('success', true);
        $block = $link->biolinkBlocks()->firstOrFail();
        $this->assertSame('https://instagram.com/sayzioapp', $block->settings['platforms'][0]['url']);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('native_block_save', $conversation->messages()->latest('id')->first()->meta['source']);
        $this->assertSame(0, $conversation->fresh()->credits_spent);
    }

    public function test_preparing_a_social_block_cannot_access_another_owners_page(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($actor);
        $workspace = app(WorkspaceContext::class)->resolve($actor);
        app()->instance('current_workspace', $workspace);
        $id = DB::table('links')->insertGetId(['user_id' => $other->id, 'workspace_id' => $workspace->id, 'type' => 'biolink', 'alias' => 'foreign-social', 'title' => 'Other page', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $r = app(VoiceToolRegistry::class)->execute($actor, false, 'prepare_social_block', ['link_id' => $id, 'platforms' => []]);
        $this->assertArrayHasKey('error', $r);
        $this->assertArrayNotHasKey('blocks', $r);
    }

    public function test_free_feature_directory_uses_real_routes_and_current_page_knowledge(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $workspace = app(WorkspaceContext::class)->resolve($actor);
        app()->instance('current_workspace', $workspace);
        $link = Link::create(['user_id' => $actor->id, 'workspace_id' => $workspace->id, 'type' => 'biolink', 'alias' => 'zio-known-page', 'title' => 'Known page', 'is_active' => true]);
        $data = ZioPageKnowledge::openFeature('Clients');
        $this->assertSame(route('user.vault.clients.index'), $data['navigate_to']);
        $context = ZioPageKnowledge::context($actor, ['path' => '/user/links/'.$link->id.'/settings/appearance']);
        $this->assertStringContainsString('Known page', $context);
        $this->assertStringContainsString('NEVER a social media block', $context);
        $this->assertStringContainsString(route('user.links.blocks.editor', $link), $context);
        $this->assertArrayHasKey('error', ZioPageKnowledge::openFeature('nonexistent-feature'));
    }
}
