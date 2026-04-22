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
 * Each citation row in the Persona/Coach grounding block must render
 * the source title as a link to the Mind source detail page so creators
 * can click through and verify what the AI actually pulled from.
 */
class MindCitationLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeUser(): User
    {
        return User::create([
            'name'     => 'Cite Tester',
            'email'    => 'cite-' . Str::random(8) . '@example.com',
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    protected function seedMindWithSource(int $userId): array
    {
        $mind = AiMind::create([
            'user_id'     => $userId,
            'name'        => 'Verifiable Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'Verifiable source title',
            'body'    => 'VERIFIABLE-SOURCE-BODY-CONTENT',
            'status'  => AiMindSource::STATUS_READY,
        ]);
        AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => 'VERIFIABLE-SOURCE-BODY-CONTENT',
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);
        return [$mind, $src];
    }

    public function test_persona_citation_renders_as_link_to_source_detail(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Indie founders shipping side projects',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]);
        $expected = 'href="' . e($href) . '"';

        $this->actingAs($user)
            ->get(route('user.ai.persona.show'))
            ->assertOk()
            ->assertSee($expected, false)
            ->assertSee('Verifiable source title');
    }

    public function test_coach_citation_renders_as_link_to_source_detail(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user);
        $link = Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'short',
            'alias'        => Str::random(7),
            'title'        => 'Demo link',
            'long_url'     => 'https://example.com/x',
            'is_active'    => true,
        ]);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]);
        $expected = 'href="' . e($href) . '"';

        $this->actingAs($user)
            ->get(route('user.ai.coach.show'))
            ->assertOk()
            ->assertSee($expected, false);
    }

    public function test_source_detail_page_shows_body_to_owner(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]))
            ->assertOk()
            ->assertSee('Verifiable source title')
            ->assertSee('VERIFIABLE-SOURCE-BODY-CONTENT');
    }

    public function test_source_detail_page_forbidden_for_other_user(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($owner->id);

        $this->actingAs($other)
            ->get(route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]))
            ->assertForbidden();
    }

    public function test_source_detail_404_when_source_belongs_to_different_mind(): void
    {
        $user = $this->makeUser();
        [$mindA, $srcA] = $this->seedMindWithSource($user->id);
        $mindB = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'Other Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mindB->id, 'source' => $srcA->id]))
            ->assertNotFound();
    }
}
