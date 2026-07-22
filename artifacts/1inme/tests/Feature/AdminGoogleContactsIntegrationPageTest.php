<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGoogleContactsIntegrationPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name'     => 'GC Admin',
            'email'    => 'gc-admin-' . uniqid() . '@example.com',
            'password' => 'secret-password',
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);
    }

    public function test_edit_page_renders_with_callback_url(): void
    {
        $resp = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.integrations.google-contacts.edit'));

        $resp->assertOk();
        $resp->assertSee(route('user.contacts.google.callback'), false);
    }

    public function test_saving_credentials_updates_settings_and_status(): void
    {
        $resp = $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.integrations.google-contacts.update'), [
                'client_id'     => 'test-client-id.apps.googleusercontent.com',
                'client_secret' => 'test-secret-value',
            ]);

        $resp->assertRedirect(route('admin.integrations.google-contacts.edit'));
        $resp->assertSessionHas('success');

        $this->assertSame('test-client-id.apps.googleusercontent.com', PlatformServiceSettings::googleContactsClientId());
        $this->assertSame('test-secret-value', PlatformServiceSettings::googleContactsClientSecret());
    }
}
