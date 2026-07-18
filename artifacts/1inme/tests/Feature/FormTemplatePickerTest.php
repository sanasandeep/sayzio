<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Controllers\FormController;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\User;
use App\Modules\User\Services\FormTemplateCatalog;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Confirms the form template picker stays forward-compatible if a catalog key
 * is renamed or removed (Task #4630).
 *
 * The store controller validates `template` against
 * FormTemplateCatalog::keys() dynamically, and FormController::templateFields()
 * falls back to Form::defaultFields() for any key it cannot resolve. This test
 * locks down:
 *   (a) every catalog key resolves to a non-empty field list (except "blank");
 *   (b) the always-present "contact" and "blank" keys never disappear;
 *   (c) an unknown / removed key is REJECTED by validation on create (not
 *       silently accepted) — while a key that survives only in an already-stored
 *       form degrades gracefully to the default field set instead of 500ing.
 */
class FormTemplatePickerTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    /* ------------------------- catalog integrity ------------------------- */

    public function test_contact_and_blank_keys_are_always_present(): void
    {
        $keys = FormTemplateCatalog::keys();

        $this->assertContains('contact', $keys, 'the default "contact" template must always exist');
        $this->assertContains('blank', $keys, 'the "blank" (start-from-scratch) template must always exist');

        $this->assertTrue(FormTemplateCatalog::isValid('contact'));
        $this->assertTrue(FormTemplateCatalog::isValid('blank'));
    }

    public function test_every_catalog_key_resolves_to_fields_except_blank(): void
    {
        $keys = FormTemplateCatalog::keys();
        $this->assertNotEmpty($keys, 'the catalog must expose at least one template');

        foreach ($keys as $key) {
            $fields = FormTemplateCatalog::fieldsFor($key);

            if ($key === 'blank') {
                $this->assertSame([], $fields, '"blank" is intentionally empty');
                continue;
            }

            $this->assertNotEmpty($fields, "template [{$key}] must resolve to a non-empty field list");

            foreach ($fields as $field) {
                $this->assertArrayHasKey('id', $field, "a field in [{$key}] is missing its id");
                $this->assertArrayHasKey('type', $field, "a field in [{$key}] is missing its type");
                $this->assertArrayHasKey('label', $field, "a field in [{$key}] is missing its label");
            }
        }
    }

    public function test_default_contact_template_has_the_expected_seeded_fields(): void
    {
        $contact = FormTemplateCatalog::fieldsFor('contact');
        $ids = array_column($contact, 'id');

        $this->assertContains('name', $ids);
        $this->assertContains('email', $ids);
        $this->assertContains('message', $ids);
    }

    /* --------------------------- create flow ---------------------------- */

    public function test_creating_a_form_with_a_valid_non_default_template_seeds_its_fields(): void
    {
        $user = $this->user($this->plan());

        $resp = $this->actingAs($user)->post(route('user.forms.store'), [
            'title'    => 'Lead Magnet',
            'template' => 'lead',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHasNoErrors();

        $form = $user->forms()->latest()->firstOrFail();
        // assertEquals (not assertSame): the field arrays survive a JSON DB
        // round-trip that can reorder the associative keys within each field.
        $this->assertEquals(
            FormTemplateCatalog::fieldsFor('lead'),
            $form->fields,
            'the created form must be seeded with the chosen (non-default) template fields'
        );
    }

    public function test_creating_a_form_without_a_template_defaults_to_contact(): void
    {
        $user = $this->user($this->plan());

        $resp = $this->actingAs($user)->post(route('user.forms.store'), [
            'title' => 'No Template Picked',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHasNoErrors();

        $form = $user->forms()->latest()->firstOrFail();
        $this->assertEquals(
            FormTemplateCatalog::fieldsFor('contact'),
            $form->fields,
            'omitting the template must fall back to the default "contact" template'
        );
    }

    public function test_unknown_template_key_is_rejected_by_validation(): void
    {
        $user = $this->user($this->plan());

        // Simulate a template key that was renamed away or removed from the
        // catalog — the picker must not silently accept it.
        $resp = $this->actingAs($user)->post(route('user.forms.store'), [
            'title'    => 'Bogus Template',
            'template' => 'this_template_no_longer_exists',
        ]);

        $resp->assertSessionHasErrors('template');
        $this->assertSame(0, $user->forms()->count(), 'no form may be created from an invalid template key');
    }

    /* -------------------- graceful fallback for stored keys -------------------- */

    public function test_template_fields_falls_back_to_default_for_a_removed_key(): void
    {
        // templateFields() is the last line of defense: if a form was created
        // against a key that has since been renamed/removed, it must degrade to
        // Form::defaultFields() rather than throwing.
        $controller = new FormController();
        $ref = new \ReflectionMethod($controller, 'templateFields');
        $ref->setAccessible(true);

        $this->assertFalse(FormTemplateCatalog::isValid('renamed_or_removed_key'));

        $this->assertSame(
            Form::defaultFields(),
            $ref->invoke($controller, 'renamed_or_removed_key'),
            'an unresolved template key must fall back to the default field set'
        );

        // A valid key still resolves to the catalog fields.
        $this->assertSame(
            FormTemplateCatalog::fieldsFor('survey'),
            $ref->invoke($controller, 'survey')
        );
    }
}
