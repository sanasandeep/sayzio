<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\SiteCustomCode;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiteCustomCodeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'guard' => 'admin']);

        return Admin::create(['name' => 'Test Admin', 'email' => 'code@example.com', 'password' => Hash::make('secret'), 'role_id' => $role->id, 'status' => 'active']);
    }

    private function codes(): array
    {
        $result = [];
        foreach (SiteCustomCode::AREAS as $area => $label) {
            $result[$area] = ['enabled' => true, 'css' => "body { --$area-test: 1; }", 'head_js' => "window.$area='head';", 'body_js' => "window.$area+='body';", 'head_html' => "<meta name=\"$area-head-test\" content=\"1\">", 'body_start_html' => "<div id=\"$area-body-start\"></div>", 'body_end_html' => "<div id=\"$area-body-end\"></div>"];
        }

        return $result;
    }

    public function test_admin_can_save_reload_and_disable_code_without_executing_it_in_the_editor(): void
    {
        $admin = $this->admin();
        $code = $this->codes();
        $code['marketing']['head_html'] .= '<script>window.editorMustNotRun=true;</script>';
        $this->actingAs($admin, 'admin')->put(route('admin.site-custom-code.update'), ['code' => $code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($code['marketing']['body_js'], SiteCustomCode::settings()['marketing']['body_js']);
        $response = $this->get(route('admin.site-custom-code.edit'))->assertOk();
        $response->assertSee('&lt;script&gt;window.editorMustNotRun=true;&lt;/script&gt;', false)->assertDontSee('<script>window.editorMustNotRun=true;</script>', false);
        $this->get('/')->assertOk()->assertSee('marketing-head-test', false);
        $code['marketing']['enabled'] = false;
        $this->put(route('admin.site-custom-code.update'), ['code' => $code])->assertRedirect();
        $this->get('/')->assertOk()->assertDontSee('marketing-head-test', false);
        $this->assertSame($code['marketing']['body_js'], SiteCustomCode::settings()['marketing']['body_js']);
        if (getenv('CUSTOM_CODE_UI_FIXTURE')) {
            file_put_contents('/tmp/site-code-admin.html', $response->getContent());
        }
    }

    public function test_only_settings_admins_can_write_code(): void
    {
        $this->put(route('admin.site-custom-code.update'), ['code' => $this->codes()])->assertRedirect();
        $this->actingAs(User::factory()->create())->put(route('admin.site-custom-code.update'), ['code' => $this->codes()])->assertRedirect();
        $role = Role::firstOrCreate(['slug' => 'code-no-permission'], ['name' => 'No settings', 'guard' => 'admin']);
        $admin = $this->admin();
        $admin->update(['role_id' => $role->id]);
        $this->actingAs($admin->fresh(), 'admin')->put(route('admin.site-custom-code.update'), ['code' => $this->codes()])->assertForbidden();
    }

    public function test_validation_is_atomic_and_unknown_areas_are_not_persisted(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $code = $this->codes();
        AppSetting::put(SiteCustomCode::SETTING_KEY, $code);
        $bad = $code;
        $bad['marketing']['css'] = str_repeat('x', 100001);
        $this->put(route('admin.site-custom-code.update'), ['code' => $bad])->assertSessionHasErrors('code.marketing.css');
        $this->assertSame($code['marketing']['css'], SiteCustomCode::settings()['marketing']['css']);
        $code['public_pages'] = ['enabled' => true, 'css' => 'PUBLIC-CODE'];
        $this->put(route('admin.site-custom-code.update'), ['code' => $code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('public_pages', AppSetting::get(SiteCustomCode::SETTING_KEY));
    }

    public function test_head_and_body_placements_are_exact_and_fragments_are_untouched(): void
    {
        AppSetting::put(SiteCustomCode::SETTING_KEY, $this->codes());
        foreach (['/', '/features', '/pricing', '/blogs', '/events', '/creators'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'name="marketing-head-test"'));
            $this->assertLessThan(stripos($html, '</head>'), strpos($html, 'name="marketing-head-test"'));
            $this->assertGreaterThan(stripos($html, '<body'), strpos($html, 'id="marketing-body-start"'));
            $this->assertLessThan(strripos($html, '</body>'), strpos($html, 'id="marketing-body-end"'));
            $this->assertStringNotContainsString('dashboard-head-test', $html);
            if (getenv('CUSTOM_CODE_UI_FIXTURE') && $path === '/') {
                file_put_contents('/tmp/site-code-home.html', $html);
            }
        }
        $this->get('/home/sections')->assertOk()->assertDontSee('marketing-head-test', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('marketing-head-test', false);
        $this->get('/user/login')->assertOk()->assertDontSee('marketing-head-test', false)->assertDontSee('dashboard-head-test', false);
    }

    public function test_placement_ignores_html_like_text_inside_scripts_and_comments(): void
    {
        AppSetting::put(SiteCustomCode::SETTING_KEY, $this->codes());
        $html = '<html><head><!-- <body> --><script>const hint="<body> and </head> and </body>";</script></head><body class="real"><main>Content</main></body></html>';
        $result = SiteCustomCode::inject($html, 'marketing');
        $this->assertStringContainsString('<body class="real">'."\n".'<div id="marketing-body-start"></div>', $result);
        $this->assertStringContainsString('const hint="<body> and </head> and </body>";', $result);
        $this->assertSame(1, substr_count($result, 'name="marketing-head-test"'));
        $this->assertSame(1, substr_count($result, 'id="marketing-body-end"'));
    }

    public function test_dashboard_code_is_limited_to_authenticated_user_pages(): void
    {
        AppSetting::put(SiteCustomCode::SETTING_KEY, $this->codes());
        $user = User::factory()->create(['onboarded_at' => now()->subDays(15)]);
        $this->actingAs($user);
        $response = $this->get('/user/dashboard')->assertOk()->assertSee('dashboard-head-test', false)->assertDontSee('marketing-head-test', false);
        if (getenv('CUSTOM_CODE_UI_FIXTURE')) {
            file_put_contents('/tmp/site-code-dashboard.html', $response->getContent());
        }
        Route::middleware('web')->get('/code-test/public-user',fn () => response('<html><head></head><body>Public page</body></html>'))->name('redirect.code-test');
        $this->get('/code-test/public-user')->assertOk()->assertDontSee('dashboard-head-test',false)->assertDontSee('marketing-head-test',false);
    }
}
