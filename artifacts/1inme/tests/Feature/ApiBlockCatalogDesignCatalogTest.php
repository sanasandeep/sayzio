<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\AdminBlockDesigns;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REST parity for admin-managed Block Designs (Task #6045): the mobile
 * app merges `design_catalog` from GET /api/v1/block-catalog on top of
 * its hardcoded variant mirror.
 */
class ApiBlockCatalogDesignCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Api User',
            'email'    => 'api' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function authed(User $user): static
    {
        return $this->withHeader(
            'Authorization',
            'Bearer ' . $user->createToken('test')->plainTextToken
        );
    }

    public function test_block_catalog_includes_design_catalog_with_admin_customs(): void
    {
        $saved = AdminBlockDesigns::saveVariant([
            'key' => '', 'name' => 'Mobile Neon', 'tags' => ['dark'], 'shape' => '',
            'types' => ['link'], 'style' => ['bg_color' => '#0f172a', 'text_color' => '#a5f3fc', 'border_radius' => 18],
            'enabled' => true,
        ]);
        AdminBlockDesigns::setVariantHidden('classic', true);

        $resp = $this->authed($this->makeUser())
            ->getJson('/api/v1/block-catalog')
            ->assertOk();

        $dc = $resp->json('data.design_catalog');
        $this->assertIsArray($dc);
        $this->assertSame(BlockVariantCatalog::version(), $dc['version']);
        $this->assertContains('classic', $dc['hidden']);

        $custom = collect($dc['custom'])->firstWhere('key', $saved['key']);
        $this->assertNotNull($custom);
        $this->assertSame('Mobile Neon', $custom['name']);
        $this->assertSame(['link'], $custom['types']);
        $this->assertIsArray($custom['preview']);
        $this->assertSame('#0f172a', $custom['preview']['bg']);
    }
}
