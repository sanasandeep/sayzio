<?php
namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateLinkAiBuilderNudgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_methods_appear_after_selecting_link_in_bio(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('user.links.create'))->assertOk()->assertSee('Help me choose')->assertDontSee('Build with AI');
        $this->get(route('user.links.biolink.create'))->assertOk()->assertSee('Start blank')->assertSee('Choose a template')->assertSee('name="start_mode" value="ai"', false)->assertSee('Uses coins.');
    }

    public function test_disabled_ai_cannot_be_selected(): void
    {
        AiEngineSettings::setEnabled(false);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('user.links.biolink.create'))->assertOk()->assertSee('currently unavailable')->assertDontSee('name="start_mode" value="ai"', false);
    }
}
