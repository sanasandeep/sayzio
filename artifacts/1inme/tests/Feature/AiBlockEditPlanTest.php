<?php
namespace Tests\Feature;

use App\Modules\User\Controllers\AiBlockEditorController;
use Tests\TestCase;

class AiBlockEditPlanTest extends TestCase
{
    private function state(): array
    {
        return ['title'=>'My page','design_locked'=>false,'blocks'=>[
            ['id'=>1,'type'=>'text','settings'=>['text'=>'Old copy','_style'=>['text_color'=>'#334455']],'is_active'=>true,'parent_id'=>null,'sort_order'=>0],
            ['id'=>2,'type'=>'text','settings'=>['text'=>'Keep this'],'is_active'=>true,'parent_id'=>null,'sort_order'=>1],
        ]];
    }
    public function test_selected_edit_preserves_unrelated_settings(): void
    {
        $plan = app(AiBlockEditorController::class)->normalizePlan(['updates'=>[['id'=>1,'settings'=>['text'=>'New copy']]]],$this->state(),['scope'=>'selected','ids'=>[1]],['text']);
        $this->assertCount(1,$plan['updates']);
        $this->assertSame('New copy',$plan['updates'][0]['after']['text']);
        $this->assertSame(['text_color'=>'#334455'],$plan['updates'][0]['after']['_style']);
        $this->assertSame([],$plan['delete_ids']);
    }
    public function test_selected_edit_cannot_touch_unselected_blocks(): void
    {
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['updates'=>[['id'=>2,'settings'=>['text'=>'Wrong']]]],$this->state(),['scope'=>'selected','ids'=>[1]],['text']);
    }
    public function test_selected_edit_cannot_replace_page_or_add_blocks(): void
    {
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['add'=>[['type'=>'text','settings'=>['text'=>'New']]]],$this->state(),['scope'=>'selected','ids'=>[1]],['text']);
    }
    public function test_invalid_reorder_and_internal_settings_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['order'=>[1,1]],$this->state(),['scope'=>'page'],['text']);
    }
    public function test_ai_cannot_change_internal_block_settings(): void
    {
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['updates'=>[['id'=>1,'settings'=>['_fixed'=>true]]]],$this->state(),['scope'=>'page'],['text']);
    }
    public function test_page_edit_can_add_reorder_and_change_title(): void
    {
        $plan = app(AiBlockEditorController::class)->normalizePlan(['order'=>[2,1],'add'=>[['type'=>'text','settings'=>['text'=>'Added']]],'page'=>['title'=>'Updated page','theme_color'=>'#445566']],$this->state(),['scope'=>'page'],['text']);
        $this->assertSame([2,1],$plan['order']);
        $this->assertSame('Updated page',$plan['page']['title']);
        $this->assertSame('Added',$plan['add'][0]['settings']['text']);
    }
    public function test_locked_page_colour_change_is_rejected(): void
    {
        $state=$this->state();$state['design_locked']=true;
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['page'=>['theme_color'=>'#445566']],$state,['scope'=>'page'],['text']);
    }
    public function test_container_deletion_requires_its_child(): void
    {
        $state=$this->state();$state['blocks'][0]['type']='card';$state['blocks'][1]['parent_id']=1;
        $this->expectException(\RuntimeException::class);
        app(AiBlockEditorController::class)->normalizePlan(['delete_ids'=>[1]],$state,['scope'=>'page'],['card','text']);
    }
    public function test_style_changes_are_sanitized_and_preserve_existing_content(): void
    {
        $plan = app(AiBlockEditorController::class)->normalizePlan(['updates'=>[['id'=>1,'style'=>['bg_color'=>'#ffffff','border_radius'=>99999]]]],$this->state(),['scope'=>'selected','ids'=>[1]],['text']);
        $after=$plan['updates'][0]['after'];
        $this->assertSame('Old copy',$after['text']);
        $this->assertSame('#ffffff',$after['_style']['bg_color']);
        $this->assertLessThanOrEqual(999,$after['_style']['border_radius']);
    }

}
