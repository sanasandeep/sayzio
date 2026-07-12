<?php

namespace Tests\Feature;

use App\Modules\Api\Controllers\FormController as ApiFormController;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile/REST parity for the form-template picker hardening in Task #4630.
 *
 * The web store controller validates `template` against the catalog and falls
 * back to Form::defaultFields() for an unresolvable key (see
 * FormTemplatePickerTest). The `/api/v1/forms` create path has its OWN template
 * whitelist + templateFields() fallback, and the two surfaces can silently
 * drift. This locks down the API side:
 *   (a) a valid non-default template seeds that template's field set;
 *   (b) omitting the template falls back to the default "contact" fields;
 *   (c) an unknown / removed template key is REJECTED by validation (422) and
 *       creates no form — not silently accepted;
 *   (d) templateFields() degrades a stored-but-removed key to the default field
 *       set instead of throwing.
 *
 * Uses a real Sanctum bearer token (never Sanctum::actingAs — that injects a
 * Mockery mock that TouchSessionToken cannot ->save(), 500ing every request;
 * see .agents/memory/sanctum-api-tests.md).
 */
class FormTemplatePickerApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** Invoke the protected API templateFields() without going through HTTP. */
    private function apiTemplateFields(string $template): array
    {
        $controller = new ApiFormController();
        $ref = new \ReflectionMethod($controller, 'templateFields');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $template);
    }

    public function test_creating_a_form_with_a_valid_non_default_template_seeds_its_fields(): void
    {
        $user = $this->makeUser();

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->postJson('/api/v1/forms', [
            'title'    => 'Lead Magnet',
            'template' => 'lead',
        ]);
        $resp->assertStatus(201);

        $id   = $resp->json('data.form.id');
        $form = Form::withoutGlobalScope('workspace')->find($id);
        $this->assertNotNull($form);

        // assertEquals (not assertSame): the field arrays survive a JSON DB
        // round-trip that can reorder the associative keys within each field.
        $this->assertEquals(
            $this->apiTemplateFields('lead'),
            $form->fields,
            'the created form must be seeded with the chosen (non-default) template fields'
        );
        $this->assertNotEmpty($form->fields, 'a non-default template must seed a non-empty field list');
        $this->assertContains('email', array_column($form->fields, 'id'));
    }

    public function test_creating_a_form_without_a_template_defaults_to_contact(): void
    {
        $user = $this->makeUser();

        $this->withToken($user->createToken('test')->plainTextToken);
        $resp = $this->postJson('/api/v1/forms', [
            'title' => 'No Template Picked',
        ]);
        $resp->assertStatus(201);

        $id   = $resp->json('data.form.id');
        $form = Form::withoutGlobalScope('workspace')->find($id);
        $this->assertNotNull($form);

        $this->assertEquals(
            Form::defaultFields(),
            $form->fields,
            'omitting the template must fall back to the default "contact" fields'
        );
    }

    public function test_unknown_template_key_is_rejected_by_validation(): void
    {
        $user = $this->makeUser();

        $this->withToken($user->createToken('test')->plainTextToken);
        // A template key that was renamed away or never existed must NOT be
        // silently accepted by the mobile create path.
        $resp = $this->postJson('/api/v1/forms', [
            'title'    => 'Bogus Template',
            'template' => 'this_template_no_longer_exists',
        ]);

        // The API wraps 422s in its unified envelope
        // ({error:{message,code,details}}), not Laravel's default
        // {errors:{...}} — so assert the custom shape, not assertJsonValidationErrors.
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('template', $resp->json('error.details'));
        $this->assertSame(
            0,
            Form::withoutGlobalScope('workspace')->where('user_id', $user->id)->count(),
            'no form may be created from an invalid template key'
        );
    }

    public function test_template_fields_falls_back_to_default_for_a_removed_key(): void
    {
        // Last line of defense: if a form somehow carries a key the whitelist
        // no longer covers, templateFields() must degrade to Form::defaultFields()
        // rather than throwing.
        $this->assertSame(
            Form::defaultFields(),
            $this->apiTemplateFields('renamed_or_removed_key'),
            'an unresolved template key must fall back to the default field set'
        );

        // "blank" stays intentionally empty; a valid key still resolves.
        $this->assertSame([], $this->apiTemplateFields('blank'));
        $this->assertNotEmpty($this->apiTemplateFields('survey'));
    }
}
