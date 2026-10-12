<?php
namespace Tests\Feature;

use App\Modules\User\Models\{User, Link};
use App\Modules\User\Services\{BusinessStarterCatalog, WorkspaceContext};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityCampaignPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_types_create_and_render_editable_drafts_then_published_content(): void
    {
        $user = User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY=>$ws->id]);
        foreach (['community_club','nonprofit_campaign'] as $type) {
            $this->get(route('user.links.biolink.create',['type'=>$type]))->assertOk()->assertSee(Link::typeLabel($type));
            $alias='new-'.$type;
            $this->post(route('user.links.store'),['type'=>$type,'alias'=>$alias,'title'=>'Our organization'])
                ->assertRedirect();
            $link=Link::where('alias',$alias)->firstOrFail();
            $this->assertTrue($link->isBiolinkFamily());
            $this->post(route('user.links.business-starter.store',$link),['starter'=>'starter-1'])->assertRedirect(route('user.links.blocks.editor',$link));
            $this->get(route('user.links.blocks.editor',$link))->assertOk()->assertSee('Add starter content')->assertSee('How it looks');
            $first=$link->biolinkBlocks()->where('type','paragraph')->firstOrFail();
            $this->get('/'.$alias)->assertOk()->assertDontSee($first->settings['text']);
            $first->update(['is_active'=>true]);
            $this->get('/'.$alias)->assertOk()->assertSee(explode("\n",$first->settings['text'])[0]);
            $form=$link->biolinkBlocks()->where('type','contact_form')->firstOrFail();
            $form->update(['is_active'=>true]);
            $this->postJson('/'.$alias.'/subscribe',['block_id'=>$form->id,'type'=>'contact_form','name'=>'Alex','email'=>'alex@example.com','message'=>'I would like to get involved.'])->assertOk()->assertJson(['success'=>true]);
            $this->assertDatabaseHas('subscribers',['link_id'=>$link->id,'block_id'=>$form->id,'type'=>'contact_form','email'=>'alex@example.com']);
            if ($type==='nonprofit_campaign') {
                $this->assertTrue($link->biolinkBlocks()->where('type','donation')->exists());
                $this->assertTrue($link->biolinkBlocks()->where('type','tip_jar')->exists());
            }
        }
    }

    public function test_new_defaults_survive_existing_admin_customizations(): void
    {
        $catalog=BusinessStarterCatalog::all();
        unset($catalog['community_club'],$catalog['nonprofit_campaign']);
        $catalog['salon_spa'][0]['label']='My custom salon';
        \App\Modules\Admin\Models\AppSetting::put('business_starters',$catalog);
        $this->assertCount(5,BusinessStarterCatalog::forType('community_club'));
        $this->assertCount(5,BusinessStarterCatalog::forType('nonprofit_campaign'));
        $this->assertSame('My custom salon',BusinessStarterCatalog::forType('salon_spa')[0]['label']);
    }
}
