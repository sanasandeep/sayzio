<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Task #6039 — "Default colors" tab in the template design session.
 * Baseline colors stored on the template draft seed the `_style` of every
 * NEW block on the draft and on pages created from the template; empty =
 * inherit; existing blocks are untouched; per-block edits still win.
 */
class TemplateDefaultColorsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBridgedAdmin(): array
    {
        $email = 'tdc-admin-' . uniqid() . '@example.com';
        $user = User::create([
            'name'     => 'Tdc Admin User',
            'email'    => $email,
            'password' => Hash::make('secret'),
        ]);
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        $admin = Admin::create([
            'name'     => 'Tdc Admin',
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
            'user_id'  => $user->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return [$admin, $user];
    }

    private function makeTemplate(): PageTemplate
    {
        return PageTemplate::create([
            'name'      => 'Default Colors Tpl',
            'slug'      => 'default-colors-tpl-' . uniqid(),
            'category'  => 'general',
            'is_active' => true,
            'snapshot'  => [
                'biolink' => ['background_type' => 'color', 'background_color' => '#101828'],
                'blocks'  => [
                    ['type' => 'heading', 'settings' => ['text' => 'Hello', 'size' => 'h2']],
                ],
            ],
        ]);
    }

    private function openDraft(Admin $admin, PageTemplate $tpl): Link
    {
        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]));

        return Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)
            ->firstOrFail();
    }

    public function test_tab_visible_on_draft_and_defaults_save_and_clear(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();
        $draft = $this->openDraft($admin, $tpl);

        $this->actingAs($admin, 'admin');
        $this->actingAs($user, 'web');

        // Tab route renders for the draft.
        $this->get(route('user.links.settings.default-colors', $draft))
            ->assertOk()
            ->assertSee('Template Default Colors');

        // The tab bar shows the extra tab on the draft's settings pages.
        $this->get(route('user.links.settings.appearance', $draft))
            ->assertOk()
            ->assertSee('Default Colors');

        // Save defaults.
        $this->post(route('user.links.page-settings', $draft), [
            'template_default_colors' => [
                'text_color'        => '#111111',
                'bg_color'          => '#eeeeee',
                'border_color'      => '#222222',
                'accent_color'      => '#ff0055',
                'accent_text_color' => '#ffffff',
            ],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertEquals([
            'text_color'        => '#111111',
            'bg_color'          => '#eeeeee',
            'border_color'      => '#222222',
            'accent_color'      => '#ff0055',
            'accent_text_color' => '#ffffff',
        ], $draft->settings['biolink']['template_default_colors']);

        // Clearing every field removes the key entirely (all-inherit).
        $this->post(route('user.links.page-settings', $draft), [
            'template_default_colors' => [
                'text_color' => '', 'bg_color' => '', 'border_color' => '',
                'accent_color' => '', 'accent_text_color' => '',
            ],
        ])->assertRedirect();
        $draft->refresh();
        $this->assertArrayNotHasKey('template_default_colors', (array) $draft->settings['biolink']);
    }

    public function test_tab_redirects_away_on_a_normal_biolink(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $this->actingAs($user, 'web');
        $link = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-normal-' . uniqid(),
            'url'     => '',
        ]);

        $this->get(route('user.links.settings.default-colors', $link))
            ->assertRedirect(route('user.links.settings.appearance', $link));
        $this->get(route('user.links.settings.appearance', $link))
            ->assertOk()
            ->assertDontSee('settings/default-colors');
    }

    public function test_new_blocks_seed_default_colors_and_stay_editable(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();
        $draft = $this->openDraft($admin, $tpl);

        $settings = $draft->settings;
        $settings['biolink']['template_default_colors'] = [
            'text_color'        => '#111111',
            'bg_color'          => '#eeeeee',
            'border_color'      => '#222222',
            'accent_color'      => '#ff0055',
            'accent_text_color' => '#fafafa',
        ];
        $draft->settings = $settings;
        $draft->save();

        $existing = $draft->biolinkBlocks()->where('type', 'heading')->first();
        $existingStyle = (array) (($existing->settings ?? [])['_style'] ?? []);

        $this->actingAs($admin, 'admin');
        $this->actingAs($user, 'web');

        // A plain block gets text/bg/border.
        $resp = $this->post(route('user.links.blocks.store', $draft), [
            'type' => 'paragraph',
        ], ['X-Requested-With' => 'XMLHttpRequest']);
        $resp->assertOk();
        $para = $draft->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertSame('#111111', $para->settings['_style']['text_color']);
        $this->assertSame('#eeeeee', $para->settings['_style']['bg_color']);
        $this->assertSame('#222222', $para->settings['_style']['border_color']);

        // A button-like block gets the accent pair instead.
        $this->post(route('user.links.blocks.store', $draft), [
            'type' => 'link',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $btn = $draft->biolinkBlocks()->where('type', 'link')->orderByDesc('id')->first();
        $this->assertSame('#ff0055', $btn->settings['_style']['bg_color']);
        $this->assertSame('#fafafa', $btn->settings['_style']['text_color']);

        // Existing blocks were not restyled.
        $existing->refresh();
        $this->assertSame($existingStyle, (array) (($existing->settings ?? [])['_style'] ?? []));

        // Per-block edits still win: updating the seeded block's style
        // through the editor overrides the template default.
        $this->put(route('user.links.blocks.update', [$draft, $para]), [
            'settings' => ['text' => 'edited'],
            'style'    => ['text_color' => '#00ff00'],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $para->refresh();
        $this->assertSame('#00ff00', $para->settings['_style']['text_color']);
        // Other seeded keys survive the granular edit.
        $this->assertSame('#eeeeee', $para->settings['_style']['bg_color']);
    }

    public function test_defaults_round_trip_into_snapshot_and_pages_from_template(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();
        $draft = $this->openDraft($admin, $tpl);

        $settings = $draft->settings;
        $settings['biolink']['template_default_colors'] = [
            'text_color' => '#123456',
            'accent_color' => '#ff0055',
        ];
        $draft->settings = $settings;
        $draft->save();

        // Save the design session back into the template.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session.save', ['id' => $tpl->id]))
            ->assertRedirect();
        $tpl->refresh();
        $this->assertSame(
            ['text_color' => '#123456', 'accent_color' => '#ff0055'],
            $tpl->snapshot['biolink']['template_default_colors'] ?? null
        );

        // A page created from the template carries the defaults...
        $page = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-page-' . uniqid(),
            'url'     => '',
        ]);
        app(TemplateService::class)->applyPageToLink($page, $tpl->snapshot, true, $tpl);
        $page->refresh();
        $this->assertSame('#123456', $page->settings['biolink']['template_default_colors']['text_color'] ?? null);

        // ...and new blocks on that page are seeded from them.
        $this->actingAs($user, 'web');
        $this->post(route('user.links.blocks.store', $page), [
            'type' => 'paragraph',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $para = $page->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertSame('#123456', $para->settings['_style']['text_color']);

        // Accent maps onto button-like blocks.
        $this->post(route('user.links.blocks.store', $page), [
            'type' => 'link',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $btn = $page->biolinkBlocks()->where('type', 'link')->orderByDesc('id')->first();
        $this->assertSame('#ff0055', $btn->settings['_style']['bg_color']);
    }

    /**
     * Task #6042 — the mobile REST API block-create path seeds the same
     * template default colors as the web editor when the caller supplies
     * no `_style`; a client-sent `_style` still wins.
     */
    public function test_api_block_create_seeds_default_colors(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $page = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-api-' . uniqid(),
            'url'     => '',
        ]);
        $settings = $page->settings ?? [];
        $settings['biolink']['template_default_colors'] = [
            'text_color'        => '#111111',
            'bg_color'          => '#eeeeee',
            'border_color'      => '#222222',
            'accent_color'      => '#ff0055',
            'accent_text_color' => '#fafafa',
        ];
        $page->settings = $settings;
        $page->save();

        $token = $user->createToken('test')->plainTextToken;

        // A plain block gets text/bg/border seeded.
        $this->withToken($token)
            ->postJson("/api/v1/links/{$page->id}/blocks", ['type' => 'paragraph'])
            ->assertCreated();
        $para = $page->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertSame('#111111', $para->settings['_style']['text_color']);
        $this->assertSame('#eeeeee', $para->settings['_style']['bg_color']);
        $this->assertSame('#222222', $para->settings['_style']['border_color']);

        // A button-like block gets the accent pair instead.
        $this->withToken($token)
            ->postJson("/api/v1/links/{$page->id}/blocks", ['type' => 'link'])
            ->assertCreated();
        $btn = $page->biolinkBlocks()->where('type', 'link')->orderByDesc('id')->first();
        $this->assertSame('#ff0055', $btn->settings['_style']['bg_color']);
        $this->assertSame('#fafafa', $btn->settings['_style']['text_color']);

        // A client-sent _style wins over the template defaults.
        $this->withToken($token)
            ->postJson("/api/v1/links/{$page->id}/blocks", [
                'type'     => 'paragraph',
                'settings' => ['text' => 'custom', '_style' => ['text_color' => '#00ff00']],
            ])
            ->assertCreated();
        $custom = \App\Modules\User\Models\BiolinkBlock::where('link_id', $page->id)
            ->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertSame('custom', $custom->settings['text'] ?? null);
        $this->assertSame('#00ff00', $custom->settings['_style']['text_color']);
        $this->assertArrayNotHasKey('bg_color', $custom->settings['_style']);
    }

    /**
     * Task #6042 — no template defaults set: the API seeds the platform
     * default `_style` (inherit-from-theme colors), matching web.
     */
    public function test_api_block_create_without_defaults_seeds_platform_style(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $page = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-api-plain-' . uniqid(),
            'url'     => '',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/links/{$page->id}/blocks", ['type' => 'paragraph'])
            ->assertCreated();
        $para = $page->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $style = $para->settings['_style'] ?? null;
        $this->assertIsArray($style);
        // Colors stay empty (= inherit from theme).
        $this->assertSame('', $style['text_color'] ?? '');
        $this->assertSame('', $style['bg_color'] ?? '');
    }

    /**
     * Task #6048 — blocks inserted programmatically by the AI marketing
     * suggestion applier seed the same template default colors as blocks
     * added by hand from the web editor / mobile API.
     */
    public function test_ai_marketing_suggestion_block_seeds_default_colors(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $page = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-ai-' . uniqid(),
            'url'     => '',
        ]);
        $settings = $page->settings ?? [];
        $settings['biolink']['template_default_colors'] = [
            'text_color'        => '#111111',
            'bg_color'          => '#eeeeee',
            'border_color'      => '#222222',
            'accent_color'      => '#ff0055',
            'accent_text_color' => '#fafafa',
        ];
        $page->settings = $settings;
        $page->save();

        $strategy = new \App\Modules\User\Models\MarketingStrategy();
        $strategy->user_id       = $user->id;
        $strategy->workspace_id  = $page->workspace_id;
        $strategy->title         = 'Test plan';
        $strategy->goal          = 'Grow';
        $strategy->status        = 'ready';
        $strategy->sources       = ['links'];
        $strategy->parameters    = [];
        $strategy->strategy      = ['summary' => 'x', 'organic' => [], 'paid' => [], 'kpis' => []];
        $strategy->model         = 'gpt-4o-mini';
        $strategy->credits_spent = 1;
        $strategy->save();

        $applier = app(\App\Services\AI\MarketingSuggestionApplier::class);

        // A plain block (paragraph) gets text/bg/border seeded.
        $s1 = \App\Modules\User\Models\MarketingStrategySuggestion::create([
            'strategy_id' => $strategy->id,
            'type'        => \App\Modules\User\Models\MarketingStrategySuggestion::TYPE_ADD_BLOCK,
            'title'       => 'Add text',
            'payload'     => ['target_alias' => $page->alias, 'block_type' => 'text', 'content' => 'Hi'],
            'status'      => \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_PENDING,
        ]);
        $applier->claimAndApply($user, $s1);
        $para = $page->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertNotNull($para);
        $this->assertSame('#111111', $para->settings['_style']['text_color']);
        $this->assertSame('#eeeeee', $para->settings['_style']['bg_color']);
        $this->assertSame('#222222', $para->settings['_style']['border_color']);

        // A button-like block (link) gets the accent pair instead.
        $s2 = \App\Modules\User\Models\MarketingStrategySuggestion::create([
            'strategy_id' => $strategy->id,
            'type'        => \App\Modules\User\Models\MarketingStrategySuggestion::TYPE_ADD_BLOCK,
            'title'       => 'Add button',
            'payload'     => ['target_alias' => $page->alias, 'block_type' => 'link', 'content' => 'Shop', 'url' => 'https://example.com/shop'],
            'status'      => \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_PENDING,
        ]);
        $applier->claimAndApply($user, $s2);
        $btn = $page->biolinkBlocks()->where('type', 'link')->orderByDesc('id')->first();
        $this->assertNotNull($btn);
        $this->assertSame('#ff0055', $btn->settings['_style']['bg_color']);
        $this->assertSame('#fafafa', $btn->settings['_style']['text_color']);
    }

    /**
     * Task #6048 — no template defaults set: the AI applier seeds the
     * platform default `_style` (inherit-from-theme colors), matching web.
     */
    public function test_ai_marketing_suggestion_block_without_defaults_seeds_platform_style(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $page = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => 'tdc-ai-plain-' . uniqid(),
            'url'     => '',
        ]);

        $strategy = new \App\Modules\User\Models\MarketingStrategy();
        $strategy->user_id       = $user->id;
        $strategy->workspace_id  = $page->workspace_id;
        $strategy->title         = 'Test plan';
        $strategy->goal          = 'Grow';
        $strategy->status        = 'ready';
        $strategy->sources       = ['links'];
        $strategy->parameters    = [];
        $strategy->strategy      = ['summary' => 'x', 'organic' => [], 'paid' => [], 'kpis' => []];
        $strategy->model         = 'gpt-4o-mini';
        $strategy->credits_spent = 1;
        $strategy->save();

        $s = \App\Modules\User\Models\MarketingStrategySuggestion::create([
            'strategy_id' => $strategy->id,
            'type'        => \App\Modules\User\Models\MarketingStrategySuggestion::TYPE_ADD_BLOCK,
            'title'       => 'Add text',
            'payload'     => ['target_alias' => $page->alias, 'block_type' => 'text', 'content' => 'Hi'],
            'status'      => \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_PENDING,
        ]);
        app(\App\Services\AI\MarketingSuggestionApplier::class)->claimAndApply($user, $s);
        $para = $page->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertNotNull($para);
        $style = $para->settings['_style'] ?? null;
        $this->assertIsArray($style);
        // Colors stay empty (= inherit from theme).
        $this->assertSame('', $style['text_color'] ?? '');
        $this->assertSame('', $style['bg_color'] ?? '');
    }

    public function test_invalid_hex_rejected_and_garbage_read_side_ignored(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();
        $draft = $this->openDraft($admin, $tpl);

        $this->actingAs($admin, 'admin');
        $this->actingAs($user, 'web');

        // Validation rejects a non-hex value.
        $this->post(route('user.links.page-settings', $draft), [
            'template_default_colors' => ['text_color' => 'javascript:alert(1)'],
        ])->assertSessionHasErrors('template_default_colors.text_color');

        // Garbage stored directly (e.g. via an unsanitized snapshot merge)
        // is ignored read-side: no seeding happens.
        $settings = $draft->settings;
        $settings['biolink']['template_default_colors'] = ['text_color' => 'red', 'bogus' => '#fff'];
        $draft->settings = $settings;
        $draft->save();

        $this->post(route('user.links.blocks.store', $draft), [
            'type' => 'paragraph',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $para = $draft->biolinkBlocks()->where('type', 'paragraph')->orderByDesc('id')->first();
        $this->assertSame('', $para->settings['_style']['text_color'] ?? '');
    }
}
