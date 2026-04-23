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
        $chunk = AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => 'VERIFIABLE-SOURCE-BODY-CONTENT',
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);
        return [$mind, $src, $chunk];
    }

    public function test_persona_citation_renders_as_link_to_source_detail(): void
    {
        $user = $this->makeUser();
        [$mind, $src, $chunk] = $this->seedMindWithSource($user->id);

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Indie founders shipping side projects',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id])
            . '?chunk=' . $chunk->id;
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
        [$mind, $src, $chunk] = $this->seedMindWithSource($user->id);

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

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id])
            . '?chunk=' . $chunk->id;
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

    public function test_source_detail_highlights_chunk_passage_in_text_body(): void
    {
        $user = $this->makeUser();
        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'Highlight Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'Long form note',
            'body'    => "Intro paragraph here.\n\nThis is the cited middle passage that\nshould light up.\n\nClosing words follow.",
            'status'  => AiMindSource::STATUS_READY,
        ]);
        $chunk = AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            // Whitespace-collapsed form, like the real chunker emits.
            'content'   => 'This is the cited middle passage that should light up.',
            'tokens'    => 10,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);

        $resp = $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id])
                . '?chunk=' . $chunk->id)
            ->assertOk();

        $resp->assertSee('id="chunk-highlight"', false);
        $resp->assertSee('<mark', false);
        $resp->assertSee('cited middle passage');
    }

    public function test_source_detail_highlights_matching_faq_row(): void
    {
        $user = $this->makeUser();
        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'FAQ Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_FAQ,
            'title'   => 'Pricing FAQ',
            'body'    => json_encode([
                ['q' => 'How much does it cost?', 'a' => 'Plans start at $9 per month.'],
                ['q' => 'Do you offer refunds?',  'a' => 'Yes, within 30 days of purchase.'],
            ]),
            'status'  => AiMindSource::STATUS_READY,
        ]);
        $chunk = AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 1,
            'content'   => 'Q: Do you offer refunds? A: Yes, within 30 days of purchase.',
            'tokens'    => 10,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);

        $resp = $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id])
                . '?chunk=' . $chunk->id)
            ->assertOk();

        $resp->assertSee('id="chunk-highlight"', false);
        $resp->assertSee('Do you offer refunds?');
    }

    public function test_source_detail_falls_back_to_cited_passage_when_body_missing(): void
    {
        $user = $this->makeUser();
        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'Doc Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        // Document sources don't persist body text, so the highlighter
        // can't pinpoint the chunk in the rendered view. We still want
        // creators to see exactly what the citation pulled.
        $src = AiMindSource::create([
            'mind_id'      => $mind->id,
            'type'         => AiMindSource::TYPE_DOCUMENT,
            'title'        => 'Whitepaper.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'ai-minds/0/whitepaper.pdf',
            'mime'         => 'application/pdf',
            'size_bytes'   => 1024,
            'status'       => AiMindSource::STATUS_READY,
        ]);
        $chunk = AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 3,
            'content'   => 'This exact passage was lifted from page 4 of the whitepaper.',
            'tokens'    => 12,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);

        $resp = $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id])
                . '?chunk=' . $chunk->id)
            ->assertOk();

        $resp->assertSee('Cited passage');
        $resp->assertSee('lifted from page 4 of the whitepaper');
        $resp->assertSee('id="chunk-highlight"', false);
    }

    public function test_source_detail_ignores_chunk_param_from_other_source(): void
    {
        $user = $this->makeUser();
        [$mindA, $srcA] = $this->seedMindWithSource($user->id);
        [$mindB, $srcB, $chunkB] = $this->seedMindWithSource($user->id);

        // Chunk belongs to srcB but we request srcA's detail page.
        $resp = $this->actingAs($user)
            ->get(route('user.minds.sources.show', ['mind' => $mindA->id, 'source' => $srcA->id])
                . '?chunk=' . $chunkB->id)
            ->assertOk();

        $resp->assertDontSee('id="chunk-highlight"', false);
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
