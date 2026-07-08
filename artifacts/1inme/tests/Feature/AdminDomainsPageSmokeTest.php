<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\Domain;
use Illuminate\Support\Str;
class AdminDomainsPageSmokeTest extends TestCase
{
    public function test_admin_domains_index_renders(): void
    {
        $role = Role::firstOrCreate(['slug'=>'super-admin'],['name'=>'Super Admin']);
        $admin = Admin::create(['name'=>'Smoke Admin','email'=>'smoke-admin-'.uniqid().'@example.com','password'=>'secret-password','status'=>'active','role_id'=>$role->id]);
        foreach (['bizs.club', 'getbio.one'] as $host) {
            Domain::firstOrCreate(['domain' => $host], [
                'user_id' => null, 'type' => 'redirect', 'is_active' => true,
                'is_verified' => true, 'verified_at' => now(),
                'verification_token' => Str::random(32),
            ]);
        }
        $resp = $this->actingAs($admin, 'admin')->get(route('admin.domains.index'));
        $resp->assertOk();
        $resp->assertSee('Add Global Domain');
        $resp->assertSee('bizs.club');
        $resp->assertSee('getbio.one');
    }
}
