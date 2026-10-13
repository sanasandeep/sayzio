<?php
namespace Tests\Feature;
use App\Modules\User\Models\{User, QrCode, Project, Workspace, Link, UserFile};
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\{QrCodeCatalog, QrCodeDesignSanitizer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QrStudioLibraryRefreshTest extends TestCase
{
    use RefreshDatabase;
    private function owner(): User {
        $user=User::factory()->create(); $this->actingAs($user);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($user));
        app()->instance('workspace_owner', $user); return $user;
    }
    private function qr(User $user): QrCode {
        return QrCode::create(['user_id'=>$user->id,'name'=>'Studio QR','type'=>'url','payload'=>['url'=>'https://example.com'],'design'=>[]]);
    }
    public function test_bulk_folder_move_and_removal(): void {
        $user=$this->owner();$qr=$this->qr($user);$folder=Project::create(['user_id'=>$user->id,'name'=>'Kitchen']);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id],'action'=>'folder','project_id'=>$folder->id])->assertRedirect();
        $this->assertEquals($folder->id,$qr->fresh()->project_id);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id],'action'=>'folder'])->assertRedirect();
        $this->assertNull($qr->fresh()->project_id);
    }
    public function test_bulk_move_rejects_foreign_record_without_partial_changes(): void {
        $user=$this->owner();$qr=$this->qr($user);$foreign=$this->qr(User::factory()->create());$folder=Project::create(['user_id'=>$user->id,'name'=>'Kitchen']);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id,$foreign->id],'action'=>'folder','project_id'=>$folder->id])->assertForbidden();
        $this->assertNull($qr->fresh()->project_id);
    }
    public function test_workspace_move_clears_old_folder_and_keeps_payload(): void {
        $user=$this->owner();$qr=$this->qr($user);$folder=Project::create(['user_id'=>$user->id,'name'=>'Kitchen']);$qr->update(['project_id'=>$folder->id]);
        $link=Link::create(['user_id'=>$user->id,'type'=>'biolink','alias'=>'unchanged-destination','is_active'=>true]); $qr->update(['link_id'=>$link->id]);
        $target=Workspace::create(['owner_user_id'=>$user->id,'name'=>'Branch','slug'=>'branch']);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id],'action'=>'workspace','workspace_id'=>$target->id])->assertRedirect();
        $moved=QrCode::withoutGlobalScope('workspace')->find($qr->id);$this->assertEquals($target->id,$moved->workspace_id);$this->assertNull($moved->project_id);$this->assertEquals($link->getShortUrl(),$moved->encoded_payload);
    }
    public function test_same_workspace_move_preserves_folder(): void {
        $user=$this->owner();$qr=$this->qr($user);$folder=Project::create(['user_id'=>$user->id,'name'=>'Kitchen']);$qr->update(['project_id'=>$folder->id]);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id],'action'=>'workspace','workspace_id'=>$qr->workspace_id])->assertRedirect();
        $this->assertEquals($folder->id,$qr->fresh()->project_id);
    }
    public function test_foreign_workspace_is_rejected(): void {
        $user=$this->owner();$qr=$this->qr($user);$target=Workspace::create(['owner_user_id'=>User::factory()->create()->id,'name'=>'Private','slug'=>'private']);
        $this->post('/user/qr-codes/move-bulk',['qr_ids'=>[$qr->id],'action'=>'workspace','workspace_id'=>$target->id])->assertNotFound();
    }
    public function test_presets_use_supported_settings_and_builder_library_render(): void {
        $user=$this->owner();
        foreach(QrCodeCatalog::presets() as $preset) {
            $clean=QrCodeDesignSanitizer::sanitize($preset['design']);
            foreach(['dot_style','corner_square_style','corner_dot_style','fg_color','bg_color'] as $key) $this->assertEquals($preset['design'][$key],$clean[$key],$preset['id']);
            $this->assertEquals($preset['design']['frame']['template'],$clean['frame']['template'],$preset['id']);
        }
        $this->assertGreaterThanOrEqual(50,count(QrCodeCatalog::frameIds()));
        $this->qr($user)->update(['design'=>QrCodeDesignSanitizer::sanitize(QrCodeCatalog::presets()[3]['design'])]);
        Link::create(['user_id'=>$user->id,'type'=>'restaurant_menu','alias'=>'kitchen','title'=>'Kitchen Menu','is_active'=>true]);
        UserFile::create(['user_id'=>$user->id,'original_name'=>'Club logo.png','filename'=>'club.png','mime_type'=>'image/png','size_bytes'=>100,'type'=>'image','disk'=>'user_files','path'=>'club.png','scan_status'=>'clean']);
        UserFile::create(['user_id'=>$user->id,'original_name'=>'Quarantined.png','filename'=>'bad.png','mime_type'=>'image/png','size_bytes'=>100,'type'=>'image','disk'=>'user_files','path'=>'bad.png','scan_status'=>'infected']);
        $builder=$this->get('/user/qr-codes/create')->assertOk()->assertSee('Club logo.png')->assertDontSee('Quarantined.png');
        $library=$this->get('/user/qr-codes')->assertOk()->assertSee('qr-library-row',false)->assertSee('Move to workspace');
        if(getenv('QR_UI_FIXTURE')) { file_put_contents('/tmp/qr-builder-fixture.html',$builder->getContent());file_put_contents('/tmp/qr-library-fixture.html',$library->getContent()); }
    }
}
