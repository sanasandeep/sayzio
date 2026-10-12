<?php
namespace Tests\Feature;
use App\Modules\Admin\Models\{Admin, AppSetting, Role, Permission};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class NavigationAppearanceTest extends TestCase
{
    use RefreshDatabase;
    private function administrator(): Admin
    {
        $role = Role::create(['slug'=>'appearance-manager','name'=>'Appearance manager','guard'=>'admin']);
        $permission = Permission::firstOrCreate(['slug'=>'settings.manage'],['name'=>'Manage settings','group'=>'settings']);
        $role->permissions()->attach($permission);
        return Admin::create(['name'=>'Manager','email'=>'appearance@example.com','password'=>bcrypt('password'),'role_id'=>$role->id,'status'=>'active']);
    }
    public function test_admin_can_save_defaults_and_invalid_colors_do_not_replace_them(): void
    {
        $this->actingAs($this->administrator(), 'admin');
        $data = ['navigation_sidebar'=>'dark','navigation_topbar'=>'color','navigation_color'=>'#2357ab'];
        $this->post(route('admin.branding.update'),$data)->assertRedirect()->assertSessionHasNoErrors();
        $expected = ['sidebar'=>'dark','topbar'=>'color','color'=>'#2357ab'];
        $this->assertEquals($expected, AppSetting::get('navigation_appearance'));
        $this->get(route('admin.branding.edit'))->assertOk()->assertSee('Workspace navigation defaults');
        $this->post(route('admin.branding.update'),array_replace($data,['navigation_color'=>'url(bad)']))->assertSessionHasErrors('navigation_color');
        $this->assertEquals($expected, AppSetting::get('navigation_appearance'));
    }
    public function test_user_cannot_change_site_defaults(): void
    {
        $this->actingAs(\App\Modules\User\Models\User::factory()->create());
        $this->post(route('admin.branding.update'),['navigation_sidebar'=>'dark','navigation_topbar'=>'dark','navigation_color'=>'#123456'])->assertRedirect();
        $this->assertNull(AppSetting::get('navigation_appearance'));
    }
}
