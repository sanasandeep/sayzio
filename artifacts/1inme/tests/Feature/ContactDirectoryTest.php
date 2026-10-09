<?php
namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContactDirectoryTest extends TestCase
{
    use RefreshDatabase;
    private function directory(): array
    {
        $user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($user);
        $link = Link::create(['user_id'=>$user->id,'type'=>'contact_directory','alias'=>'directory'.fake()->unique()->numerify('#####'),'title'=>'Hotel help desk','is_active'=>true]);
        $directory = $link->contactDirectory()->create(['settings'=>['timezone'=>'UTC']]);
        return [$user,$link,$directory];
    }

    public function test_public_directory_hides_drafts_and_renders_actions_and_blocks(): void
    {
        [$user,$link,$directory] = $this->directory();
        $category = $directory->categories()->create(['name'=>'Reception']);
        $directory->contacts()->create(['category_id'=>$category->id,'name'=>'Front desk','is_active'=>true,'details'=>['phone'=>'+919876543210','location'=>'Main lobby','email'=>'desk@example.com','whatsapp'=>'919876543210','message'=>'Hello & welcome']]);
        $directory->contacts()->create(['name'=>'Private draft','is_active'=>false]);
        foreach (['top','above','section:'.$category->id,'below'] as $i=>$slot) {
            $link->biolinkBlocks()->create(['user_id'=>$user->id,'type'=>'paragraph','settings'=>['text'=>'BLOCK'.$i,'_style'=>['_menu_slot'=>$slot]],'position'=>$i,'is_active'=>true]);
        }
        $response=$this->get('/'.$link->alias);
        $response->assertOk()->assertSee('Front desk')->assertDontSee('Private draft')->assertSee('tel:+919876543210',false)->assertSee('mailto:desk@example.com',false)->assertSee('Hello%20%26%20welcome',false);
        $response->assertSeeInOrder(['BLOCK0','Hotel help desk','BLOCK1','Front desk','BLOCK2','BLOCK3']);
    }

    public function test_owner_editor_and_empty_public_page_render(): void
    {
        [$user,$link] = $this->directory();
        $this->actingAs($user)->get(route('user.links.directory.editor',$link))->assertOk()->assertSee('Add contact');
        $this->get('/'.$link->alias)->assertOk()->assertSee('This directory is being prepared');
    }

    public function test_contacts_cannot_be_assigned_to_another_directory_category(): void
    {
        [$user,$link] = $this->directory();
        [,,$other] = $this->directory();
        $category=$other->categories()->create(['name'=>'Other']);
        app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->post(route('user.links.directory.save',[$link,'contact']),['name'=>'Wrong','sort_order'=>0,'category_id'=>$category->id])->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('directory_contacts',['name'=>'Wrong']);
    }

    public function test_starters_are_drafts_and_are_idempotent(): void
    {
        [$user,$link,$directory] = $this->directory();
        for ($i=0;$i<2;$i++) $this->actingAs($user)->post(route('user.links.directory.save',[$link,'starter']),['starter'=>'hotel'])->assertRedirect();
        $this->assertSame(4,$directory->contacts()->count());
        $this->assertSame(0,$directory->contacts()->where('is_active',true)->count());
    }

    public function test_overnight_hours_follow_the_previous_working_day(): void
    {
        [,,$directory] = $this->directory();
        $contact=$directory->contacts()->create(['name'=>'Night desk','details'=>['opens'=>'22:00','closes'=>'06:00','days'=>'1']]);
        try {
            Carbon::setTestNow(Carbon::parse('2026-10-13 02:00:00','UTC'));
            $this->assertTrue($contact->availability('UTC'));
            Carbon::setTestNow(Carbon::parse('2026-10-13 22:30:00','UTC'));
            $this->assertFalse($contact->availability('UTC'));
        } finally { Carbon::setTestNow(); }
    }
}
