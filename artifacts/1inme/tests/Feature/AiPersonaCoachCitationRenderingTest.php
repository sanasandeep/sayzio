<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * UI rendering coverage for the Persona and Coach result blocks.
 *
 * The controllers stash a `citations` array and a `minds_used` echo on
 * the session after a generation. The blade views under
 * `resources/views/user/ai/{persona,coach}.blade.php` then render those
 * via `_partials/mind-breakdown.blade.php`. This test guards against
 * silent template regressions (e.g. a refactor dropping the loop)
 * by driving the full POST → redirect → GET flow and asserting the
 * rendered HTML actually contains the citation titles and Mind names
 * — not just that the controller returned them in the session.
 *
 * Two states are covered for each surface:
 *   - "no minds selected" → no Grounded-in / Sources block rendered
 *   - "minds selected → citations rendered" → Mind name + citation
 *     title appear in the response body
 */
class AiPersonaCoachCitationRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        $this->bindOpenAiMock();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Bind a deterministic OpenAiService double:
     *   - embed() returns a constant unit vector so it cosines to 1.0
     *     against the constant-vector chunk seeded below, guaranteeing
     *     the seeded chunk is the top-K hit.
     *   - chat() returns a fixed string so we can assert on it.
     */
    protected function bindOpenAiMock(): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('embed')->andReturnUsing(
            fn ($user, $model, $batch, $opts = []) => [
                'vectors'       => array_map(fn () => [1.0], $batch),
                'tokens_in'     => 0,
                'credits_spent' => 0,
                'model'         => $model,
            ],
        );
        $mock->shouldReceive('chat')->andReturnUsing(
            fn ($user, $model, $messages, $opts = []) => [
                'content'       => 'GENERATED-OUTPUT',
                'tokens_in'     => 0,
                'tokens_out'    => 0,
                'credits_spent' => 0,
                'model'         => $model,
                'raw'           => [],
            ],
        );
        $this->app->instance(OpenAiService::class, $mock);
    }

    protected function makeUser(string $tag = 'u'): User
    {
        return User::factory()->create([
            'name' => "Test $tag",
            'email' => $tag . '-' . Str::random(8) . '@example.com',
            'role' => 'user',
        ]);
    }

    protected function makeMindWithSource(int $userId, string $name, string $sourceTitle, string $body): AiMind
    {
        $mind = AiMind::create([
            'user_id'     => $userId,
            'name'        => $name,
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => $sourceTitle,
            'body'    => $body,
            'status'  => AiMindSource::STATUS_READY,
        ]);
        AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => $body,
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);
        return $mind;
    }

    protected function makeLink(User $user): Link
    {
        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user);
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'short',
            'alias'        => Str::random(7),
            'title'        => 'Demo link',
            'long_url'     => 'https://example.com/x',
            'is_active'    => true,
        ]);
    }

    // ---------- Persona ----------

    public function test_persona_view_omits_grounding_block_when_no_minds_selected(): void
    {
        $user = $this->makeUser('p-empty');

        // Generate without any mind_ids and without opting into the
        // platform mind — minds_used and citations must both be empty.
        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Solo founders launching their first SaaS',
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $html = $this->actingAs($user)
            ->get(route('user.ai.persona.show'))
            ->assertOk()
            ->getContent();

        // The result block itself should render (the generation did
        // succeed) but neither breakdown variant should appear.
        $this->assertStringContainsString('GENERATED-OUTPUT', $html);
        $this->assertStringNotContainsString('Grounded in', $html);
        $this->assertStringNotContainsString('>Sources<', $html);
    }

    public function test_persona_view_renders_mind_name_and_citation_title(): void
    {
        $user = $this->makeUser('p-cite');
        $mind = $this->makeMindWithSource(
            $user->id,
            'Brand Voice Mind',
            'Brand voice guide',
            'BRAND-VOICE-GUIDE-CONTENT',
        );

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Yoga teachers building a studio brand',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $response = $this->actingAs($user)
            ->get(route('user.ai.persona.show'))
            ->assertOk();

        $response->assertSee('GENERATED-OUTPUT');
        $response->assertSee('Grounded in');
        // Mind name appears in the breakdown header.
        $response->assertSee('Brand Voice Mind');
        // Citation title appears in the per-mind citation list.
        $response->assertSee('Brand voice guide');
    }

    // ---------- Coach ----------

    public function test_coach_view_omits_grounding_block_when_no_minds_selected(): void
    {
        $user = $this->makeUser('c-empty');
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id' => $link->id,
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $html = $this->actingAs($user)
            ->get(route('user.ai.coach.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('GENERATED-OUTPUT', $html);
        $this->assertStringNotContainsString('Grounded in', $html);
        $this->assertStringNotContainsString('>Sources<', $html);
    }

    public function test_persona_view_renders_open_original_link_for_link_citations(): void
    {
        $user = $this->makeUser('p-link');
        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'Link Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_LINK,
            'title'   => 'External article',
            'url'     => 'https://example.com/original-article',
            'body'    => 'CACHED-LINK-BODY',
            'status'  => AiMindSource::STATUS_READY,
        ]);
        AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => 'CACHED-LINK-BODY',
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Indie hackers',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $html = $this->actingAs($user)
            ->get(route('user.ai.persona.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('https://example.com/original-article', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('Open original page in new tab', $html);
    }

    public function test_coach_view_renders_mind_name_and_citation_title(): void
    {
        $user = $this->makeUser('c-cite');
        $mind = $this->makeMindWithSource(
            $user->id,
            'Coach Playbook Mind',
            'Coach playbook',
            'COACH-PLAYBOOK-FACTS',
        );
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $response = $this->actingAs($user)
            ->get(route('user.ai.coach.show'))
            ->assertOk();

        $response->assertSee('GENERATED-OUTPUT');
        $response->assertSee('Grounded in');
        $response->assertSee('Coach Playbook Mind');
        $response->assertSee('Coach playbook');
    }
}
