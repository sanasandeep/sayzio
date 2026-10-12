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
        $data = ['navigation_sidebar'=>'dark','navigation_topbar'=>'gradient-dark','navigation_logo'=>'1'];
        $this->post(route('admin.branding.update'),$data)->assertRedirect()->assertSessionHasNoErrors();
        $expected = ['sidebar'=>'dark','topbar'=>'gradient-dark','logo'=>true];
        $this->assertEquals($expected, AppSetting::get('navigation_appearance'));
        $this->get(route('admin.branding.edit'))->assertOk()->assertSee('Workspace navigation defaults');
        $this->post(route('admin.branding.update'),array_replace($data,['navigation_topbar'=>'unknown']))->assertSessionHasErrors('navigation_topbar');
        $this->assertEquals($expected, AppSetting::get('navigation_appearance'));
    }
    public function test_design_settings_are_accessible_in_the_settings_hub(): void
    {
        $user = \App\Modules\User\Models\User::factory()->create(['onboarded_at'=>now()->subMonths(2)]);
        $this->actingAs($user)->get(route('user.design.edit'))->assertOk()->assertSee('Light gradient')->assertSee('Include in top bar style')->assertDontSee('Navigation appearance');
        $this->assertSame('user.design.edit', \App\Modules\User\Support\SettingsTabs::tabs()['design']['route']);
    }
    public function test_user_cannot_change_site_defaults(): void
    {
        $this->actingAs(\App\Modules\User\Models\User::factory()->create());
        $this->post(route('admin.branding.update'),['navigation_sidebar'=>'dark','navigation_topbar'=>'dark','navigation_color'=>'#123456'])->assertRedirect();
        $this->assertNull(AppSetting::get('navigation_appearance'));
    }
}
