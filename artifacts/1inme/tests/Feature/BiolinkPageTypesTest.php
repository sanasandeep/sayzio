<?php

namespace Tests\Feature;

use App\Modules\Common\Controllers\RedirectController;
use App\Modules\User\Controllers\AiChatController;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the three "new" biolink-family page types (conversational, slides,
 * full-page ai_chat) promoted to first-class `links.type` values:
 *
 *  - RedirectController::biolinkViewFor() picks the right public view per
 *    link type, including the legacy settings.biolink.mode fallback for
 *    rows that predate the data migration.
 *  - The data migration that promotes type=biolink rows whose
 *    settings.biolink.mode is conversational/slides (and is idempotent).
 *  - AiChatController auto-provisions a default persona + AiCompanion
 *    (placement=page) and binds it to the link via ai_companion_links.
 *  - ai_chat with no bound / disabled companion never dead-ends — it falls
 *    back to the static block page.
 */
class BiolinkPageTypesTest extends TestCase
{
    use RefreshDatabase;

    // ───────────────────────── helpers ─────────────────────────

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'pt' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeLink(User $user, string $type = 'biolink', array $settings = []): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => $type,
            'alias'     => Link::generateAlias(),
            'title'     => 'Bio',
            'is_active' => true,
            'settings'  => $settings,
        ]);
    }

    /** Invoke the protected RedirectController::biolinkViewFor() in isolation. */
    private function viewFor(Link $link): string
    {
        $controller = app(RedirectController::class);
        $m = new ReflectionMethod($controller, 'biolinkViewFor');
        $m->setAccessible(true);
        return $m->invoke($controller, $link);
    }

    private function publishConversation(Link $link): ConversationFlow
    {
        return ConversationFlow::create([
            'link_id'      => $link->id,
            'name'         => 'Flow',
            'is_published' => true,
            'is_active'    => true,
        ]);
    }

    private function publishDeck(Link $link): LinkSlideDeck
    {
        return LinkSlideDeck::create([
            'link_id'      => $link->id,
            'is_published' => true,
            'settings'     => [],
        ]);
    }

    // ───────────── biolinkViewFor: type → view selection ─────────────

    public function test_plain_biolink_renders_block_page(): void
    {
        $link = $this->makeLink($this->makeUser(), 'biolink');
        $this->assertSame('common.biolink', $this->viewFor($link));
    }

    public function test_conversational_type_renders_chat_view_when_published(): void
    {
        $link = $this->makeLink($this->makeUser(), 'conversational');
        $this->publishConversation($link);
        $this->assertSame('common.biolink-conversational', $this->viewFor($link));
    }

    public function test_conversational_type_falls_back_when_no_published_flow(): void
    {
        $link = $this->makeLink($this->makeUser(), 'conversational');
        // No flow at all → fall back to the block page (never a dead end).
        $this->assertSame('common.biolink', $this->viewFor($link));

        // An unpublished flow is also not enough for a public visitor.
        ConversationFlow::create([
            'link_id'      => $link->id,
            'name'         => 'Draft',
            'is_published' => false,
            'is_active'    => true,
        ]);
        $this->assertSame('common.biolink', $this->viewFor($link));
    }

    public function test_slides_type_renders_slides_view_when_published(): void
    {
        $link = $this->makeLink($this->makeUser(), 'slides');
        $this->publishDeck($link);
        $this->assertSame('common.biolink-slides', $this->viewFor($link));
    }

    public function test_slides_type_falls_back_when_no_published_deck(): void
    {
        $link = $this->makeLink($this->makeUser(), 'slides');
        $this->assertSame('common.biolink', $this->viewFor($link));

        LinkSlideDeck::create([
            'link_id'      => $link->id,
            'is_published' => false,
            'settings'     => [],
        ]);
        $this->assertSame('common.biolink', $this->viewFor($link));
    }

    public function test_ai_chat_type_renders_chat_view_when_companion_bound(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'ai_chat');
        $this->bindCompanion($link, $user);
        $this->assertSame('common.ai-chat', $this->viewFor($link));
    }

    public function test_ai_chat_type_falls_back_with_no_companion(): void
    {
        $link = $this->makeLink($this->makeUser(), 'ai_chat');
        $this->assertSame('common.biolink', $this->viewFor($link));
    }

    public function test_ai_chat_type_falls_back_with_disabled_companion(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'ai_chat');
        $this->bindCompanion($link, $user, disabled: true);
        $this->assertSame('common.biolink', $this->viewFor($link));
    }

    // ───────────── biolinkViewFor: legacy settings.biolink.mode fallback ─────────────

    public function test_legacy_biolink_mode_conversational_still_renders_chat(): void
    {
        $link = $this->makeLink($this->makeUser(), 'biolink', ['biolink' => ['mode' => 'conversational']]);
        $this->publishConversation($link);
        $this->assertSame('common.biolink-conversational', $this->viewFor($link));
    }

    public function test_legacy_biolink_mode_slides_still_renders_slides(): void
    {
        $link = $this->makeLink($this->makeUser(), 'biolink', ['biolink' => ['mode' => 'slides']]);
        $this->publishDeck($link);
        $this->assertSame('common.biolink-slides', $this->viewFor($link));
    }

    // ───────────── data migration: mode → type promotion ─────────────

    public function test_migration_promotes_legacy_mode_rows_and_is_idempotent(): void
    {
        $user = $this->makeUser();
        $conv   = $this->makeLink($user, 'biolink', ['biolink' => ['mode' => 'conversational']]);
        $slides = $this->makeLink($user, 'biolink', ['biolink' => ['mode' => 'slides']]);
        $plain  = $this->makeLink($user, 'biolink', ['biolink' => ['mode' => 'list']]);
        $noMode = $this->makeLink($user, 'biolink', []);

        $migration = require database_path('migrations/2027_05_17_000001_migrate_biolink_mode_to_link_type.php');

        $migration->up();

        $this->assertSame('conversational', $this->typeOf($conv));
        $this->assertSame('slides', $this->typeOf($slides));
        $this->assertSame('biolink', $this->typeOf($plain));
        $this->assertSame('biolink', $this->typeOf($noMode));

        // Re-running must be a no-op (only rows still typed `biolink` are touched).
        $migration->up();
        $this->assertSame('conversational', $this->typeOf($conv));
        $this->assertSame('slides', $this->typeOf($slides));
        $this->assertSame('biolink', $this->typeOf($plain));
    }

    public function test_migration_down_folds_types_back_to_biolink(): void
    {
        $user = $this->makeUser();
        $conv   = $this->makeLink($user, 'conversational', ['biolink' => ['mode' => 'conversational']]);
        $slides = $this->makeLink($user, 'slides', ['biolink' => ['mode' => 'slides']]);

        $migration = require database_path('migrations/2027_05_17_000001_migrate_biolink_mode_to_link_type.php');
        $migration->down();

        $this->assertSame('biolink', $this->typeOf($conv));
        $this->assertSame('biolink', $this->typeOf($slides));
    }

    // ───────────── AiChatController auto-provisioning ─────────────

    public function test_ensure_companion_auto_provisions_persona_and_binds_page_companion(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'ai_chat');

        $this->assertSame(0, AiPersonaAgent::where('user_id', $user->id)->count());

        $companion = $this->ensureCompanion($link, $user);

        // A dedicated default persona was created for the user…
        $this->assertSame(1, AiPersonaAgent::where('user_id', $user->id)->count());
        $this->assertNotNull($companion->persona_id);

        // …and the companion is a full-page placement bound to the link.
        $this->assertSame(AiCompanion::PLACEMENT_PAGE, $companion->placement);
        $this->assertSame($user->id, $companion->user_id);
        $this->assertDatabaseHas('ai_companion_links', [
            'companion_id' => $companion->id,
            'link_id'      => $link->id,
        ]);

        // The link now resolves its bound companion and renders the chat view.
        $link->refresh();
        $this->assertNotNull($link->aiCompanion());
        $this->assertSame('common.ai-chat', $this->viewFor($link));
    }

    public function test_ensure_companion_reuses_existing_user_persona(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'ai_chat');

        $persona = AiPersonaAgent::create([
            'user_id'           => $user->id,
            'slug'              => 'existing-' . Str::lower(Str::random(6)),
            'name'              => 'Existing Persona',
            'description'       => 'x',
            'system_prompt'     => 'You help.',
            'model'             => \App\Services\AI\AiEngineSettings::DEFAULT_FEATURE_MODEL,
            'temperature_x100'  => 70,
            'max_tokens'        => 600,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => true,
        ]);

        $companion = $this->ensureCompanion($link, $user);

        // No second persona was minted — the user's existing one is reused.
        $this->assertSame(1, AiPersonaAgent::where('user_id', $user->id)->count());
        $this->assertSame($persona->id, $companion->persona_id);
    }

    public function test_ensure_companion_is_idempotent_for_a_link(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'ai_chat');

        $first  = $this->ensureCompanion($link, $user);
        $link->refresh();
        $second = $this->ensureCompanion($link, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AiCompanion::where('user_id', $user->id)->count());
    }

    // ───────────────────────── internal helpers ─────────────────────────

    private function typeOf(Link $link): string
    {
        return (string) DB::table('links')->where('id', $link->id)->value('type');
    }

    private function ensureCompanion(Link $link, User $user): AiCompanion
    {
        $controller = app(AiChatController::class);
        $m = new ReflectionMethod($controller, 'ensureCompanion');
        $m->setAccessible(true);
        return $m->invoke($controller, $link, $user);
    }

    private function bindCompanion(Link $link, User $user, bool $disabled = false): AiCompanion
    {
        $persona = AiPersonaAgent::create([
            'user_id'           => $user->id,
            'slug'              => 'p-' . Str::lower(Str::random(8)),
            'name'              => 'Persona',
            'description'       => 'x',
            'system_prompt'     => 'You help.',
            'model'             => \App\Services\AI\AiEngineSettings::DEFAULT_FEATURE_MODEL,
            'temperature_x100'  => 70,
            'max_tokens'        => 600,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => true,
        ]);

        $companion = AiCompanion::create([
            'user_id'              => $user->id,
            'persona_id'           => $persona->id,
            'public_id'            => AiCompanion::newPublicId(),
            'name'                 => 'Chat',
            'placement'            => AiCompanion::PLACEMENT_PAGE,
            'config'               => AiCompanion::defaultConfig(),
            'allowed_domains'      => [],
            'free_turns_per_month' => 50,
            'hard_cap_per_month'   => 2000,
            'is_disabled'          => $disabled,
        ]);
        $companion->links()->syncWithoutDetaching([$link->id]);

        return $companion;
    }
}
