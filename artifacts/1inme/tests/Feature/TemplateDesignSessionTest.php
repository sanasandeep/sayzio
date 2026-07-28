<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin "visual design editor" for page templates: opening a session
 * materialises the template snapshot onto a hidden draft biolink owned by
 * the admin's bridged user and redirects into the REAL biolink editor;
 * "Save to template" captures the draft back into the snapshot and deletes
 * the draft; "Discard" deletes the draft leaving the template untouched.
 */
class TemplateDesignSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBridgedAdmin(): array
    {
        $email = 'tpl-admin-' . uniqid() . '@example.com';
        $user = User::create([
            'name'     => 'Tpl Admin User',
            'email'    => $email,
            'password' => Hash::make('secret'),
        ]);
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        $admin = Admin::create([
            'name'     => 'Tpl Admin',
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
            'user_id'  => $user->id,
        ]);
        return [$admin, $user];
    }

    private function makeTemplate(): PageTemplate
    {
        return PageTemplate::create([
            'name'     => 'Design Session Tpl',
            'slug'     => 'design-session-tpl-' . uniqid(),
            'category' => 'general',
            'is_active' => true,
            'snapshot' => [
                'biolink' => ['background_type' => 'color', 'background_color' => '#101828'],
                'blocks'  => [
                    ['type' => 'heading', 'settings' => ['text' => 'Hello', 'size' => 'h2']],
                    ['type' => 'paragraph', 'settings' => ['text' => 'World']],
                ],
            ],
        ]);
    }

    public function test_open_session_creates_draft_and_redirects_to_editor(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]));

        $draft = Link::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->where('settings->_template_draft->template_id', $tpl->id)
            ->first();

        $this->assertNotNull($draft, 'A draft biolink should be created for the session');
        $resp->assertRedirect(route('user.links.blocks.editor', $draft));
        $this->assertSame(2, $draft->biolinkBlocks()->count());
        $this->assertSame('#101828', $draft->settings['biolink']['background_color'] ?? null);

        // Reopening reuses the same draft instead of piling up new ones.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]))
            ->assertRedirect(route('user.links.blocks.editor', $draft));
        $this->assertSame(1, Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)->count());

        // The editor page shows the template-session banner.
        $page = $this->actingAs($admin, 'admin')->actingAs($user, 'web')
            ->get(route('user.links.blocks.editor', $draft));
        $page->assertOk();
        $page->assertSee('Editing template: ' . $tpl->name);
        $page->assertSee('Save to template');

        // GET also works (direct link / embedded iframe on the edit page).
        $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.design.session', ['id' => $tpl->id]))
            ->assertRedirect(route('user.links.blocks.editor', $draft));
    }

    public function test_save_captures_design_back_into_template_and_deletes_draft(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]));

        $draft = Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)->firstOrFail();

        // Simulate an edit in the editor: change background + a block text.
        $settings = $draft->settings;
        $settings['biolink']['background_color'] = '#ff0000';
        $draft->settings = $settings;
        $draft->save();
        $block = $draft->biolinkBlocks()->where('type', 'heading')->first();
        $block->settings = array_merge((array) $block->settings, ['text' => 'Edited!']);
        $block->save();

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session.save', ['id' => $tpl->id]));
        $resp->assertRedirect(route('admin.templates.edit', ['kind' => 'page', 'id' => $tpl->id]));

        $tpl->refresh();
        $this->assertSame('#ff0000', $tpl->snapshot['biolink']['background_color'] ?? null);
        $texts = collect($tpl->snapshot['blocks'])->pluck('settings.text')->all();
        $this->assertContains('Edited!', $texts);
        // The session marker never leaks into the published snapshot.
        $this->assertArrayNotHasKey('_template_draft', (array) ($tpl->snapshot['biolink'] ?? []));

        $this->assertNull(Link::withoutGlobalScope('workspace')->find($draft->id), 'Draft deleted after save');
    }

    public function test_discard_deletes_draft_without_touching_template(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();
        $before = $tpl->snapshot;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]));
        $draft = Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session.discard', ['id' => $tpl->id]))
            ->assertRedirect(route('admin.templates.edit', ['kind' => 'page', 'id' => $tpl->id]));

        $this->assertNull(Link::withoutGlobalScope('workspace')->find($draft->id));
        $this->assertSame($before, $tpl->refresh()->snapshot);
    }

    public function test_draft_is_hidden_from_public_page_and_my_links(): void
    {
        [$admin, $user] = $this->makeBridgedAdmin();
        $tpl = $this->makeTemplate();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.design.session', ['id' => $tpl->id]));
        $draft = Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)->firstOrFail();

        // Guests and other users get a 404 on the draft's public URL.
        auth()->guard('web')->logout();
        $this->get('/' . $draft->alias)->assertNotFound();

        $stranger = User::create([
            'name'     => 'Stranger',
            'email'    => 'stranger-' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
        ]);
        $this->actingAs($stranger, 'web')->get('/' . $draft->alias)->assertNotFound();

        // The owner (bridged admin user) still sees it — editor live preview.
        auth()->guard('web')->logout();
        $this->actingAs($user, 'web')->get('/' . $draft->alias)->assertOk();

        // The draft never shows up in the owner's My Links list.
        $this->actingAs($user, 'web')->get(route('user.links.index'))
            ->assertOk()
            ->assertDontSee($draft->alias);
    }

    public function test_guest_and_plain_user_cannot_open_a_session(): void
    {
        $tpl = $this->makeTemplate();

        $this->post(route('admin.templates.design.session', ['id' => $tpl->id]))
            ->assertRedirect();
        $this->assertSame(0, Link::withoutGlobalScope('workspace')
            ->where('settings->_template_draft->template_id', $tpl->id)->count());
    }
}
