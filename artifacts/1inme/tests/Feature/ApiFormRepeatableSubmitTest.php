<?php

namespace Tests\Feature;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the public REST form-submission endpoint
 * (POST /api/v1/forms/{id}/submit) — the API mirror of the web POST /f/{slug}.
 * The endpoint must collect and store repeatable-group payloads
 * (rep_{id}[idx][childId]) in the same {_repeatable_group, copies} shape as the
 * web flow, and reject payloads that miss the minimum copy count. See Task #4638.
 */
class ApiFormRepeatableSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    /** A form with a flat field plus a repeatable "Line items" section. */
    private function repeatableForm(User $owner): Form
    {
        return $owner->forms()->create([
            'title'     => 'Order Form',
            'is_active' => true,
            'settings'  => Form::defaultSettings(),
            'fields'    => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                ['id' => '7', 'type' => 'section', 'label' => 'Line items',
                 'repeatable' => true, 'repeat_min' => 1, 'repeat_add_label' => 'Add item'],
                ['id' => 'item_9', 'type' => 'text', 'label' => 'Item', 'parent' => '7'],
                ['id' => 'qty_10', 'type' => 'number', 'label' => 'Qty', 'parent' => '7'],
            ],
        ]);
    }

    public function test_repeatable_group_payload_is_stored_as_copies(): void
    {
        $form = $this->repeatableForm($this->owner());

        $res = $this->postJson("/api/v1/forms/{$form->id}/submit", [
            'name'  => 'Ada Lovelace',
            'rep_7' => [
                ['item_9' => 'Widget', 'qty_10' => '2'],
                ['item_9' => 'Gadget', 'qty_10' => '1'],
            ],
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true]);

        $submission = FormSubmission::where('form_id', $form->id)->latest('id')->first();
        $this->assertNotNull($submission, 'A submission row should be created.');

        $data = $submission->data ?? [];
        $this->assertSame('Ada Lovelace', $data['name'] ?? null);
        // assertEquals (not assertSame): the stored payload is JSON round-tripped,
        // so associative key order is not guaranteed — only the shape/values are.
        $this->assertEquals([
            '_repeatable_group' => true,
            'copies' => [
                ['item_9' => 'Widget', 'qty_10' => '2'],
                ['item_9' => 'Gadget', 'qty_10' => '1'],
            ],
        ], $data['7'] ?? null);
    }

    public function test_missing_minimum_copies_fails_validation(): void
    {
        $form = $this->repeatableForm($this->owner());

        $res = $this->postJson("/api/v1/forms/{$form->id}/submit", [
            'name' => 'No line items',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame(0, FormSubmission::where('form_id', $form->id)->count());
    }

    public function test_submit_to_inactive_form_is_not_found(): void
    {
        $owner = $this->owner();
        $form  = $this->repeatableForm($owner);
        $form->update(['is_active' => false]);

        $res = $this->postJson("/api/v1/forms/{$form->id}/submit", [
            'rep_7' => [['item_9' => 'X', 'qty_10' => '1']],
        ]);

        $res->assertStatus(404);
    }
}
