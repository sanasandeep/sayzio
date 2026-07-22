<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guards the admin block-defaults row editor across EVERY block type.
 *
 * The e2e spec exercises one type end-to-end; this test iterates all
 * canonical types and verifies that the row-editor metadata the edit
 * screen ships to the client (kind + fields per repeatable key) matches
 * the actual hardcoded system defaults — so a per-type regression in
 * arrayContentKeys() (wrong kind, missing/extra field, wrong field type)
 * fails fast instead of shipping a broken row editor.
 */
class AdminBlockDefaultsRowEditorCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /**
     * Independent oracle: derive the expected row-editor metadata for one
     * key straight from the system-default value. Returns null when the
     * key must stay JSON-only (no row editor).
     *
     * @return array{kind:string,fields?:array<string,string>}|null
     */
    private function expectedMetaFor(string $key, mixed $value): ?array
    {
        if (str_starts_with($key, '_') || !is_array($value) || $value === [] || !array_is_list($value)) {
            return null;
        }

        $allStrings = true;
        foreach ($value as $item) {
            if (!is_string($item)) {
                $allStrings = false;
                break;
            }
        }
        if ($allStrings) {
            return ['kind' => 'strings'];
        }

        $fields = [];
        foreach ($value as $item) {
            if (!is_array($item) || $item === [] || array_is_list($item)) {
                return null;
            }
            foreach ($item as $field => $v) {
                if (!is_scalar($v) && $v !== null) {
                    return null;
                }
                if (!isset($fields[$field])) {
                    $fields[$field] = is_bool($v)
                        ? 'boolean'
                        : (is_int($v) || is_float($v) ? 'number' : 'string');
                }
            }
        }

        return ['kind' => 'objects', 'fields' => $fields];
    }

    public function test_row_editor_metadata_matches_system_defaults_for_every_type(): void
    {
        $admin = $this->admin();
        $types = array_unique(BlockTypeRegistry::canonicalTypeSlugs());
        $this->assertNotEmpty($types);

        $sawStrings = false;
        $sawObjects = false;

        foreach ($types as $type) {
            $response = $this->actingAs($admin, 'admin')
                ->get(route('admin.block-defaults.edit', $type));
            $response->assertOk();

            $systemContent = $response->viewData('systemContent');
            $arrayMeta     = $response->viewData('arrayContentKeys');
            $this->assertIsArray($systemContent, "[$type] systemContent missing from view");
            $this->assertIsArray($arrayMeta, "[$type] arrayContentKeys missing from view");

            // The view's system content must equal the hardcoded defaults
            // (admin override suppressed, meta keys stripped).
            $expectedContent = BlockDefaults::withoutAdminOverrides(
                fn () => BlockDefaults::contentForType($type)
            );
            unset($expectedContent['_placeholder'], $expectedContent['_style']);
            $this->assertSame($expectedContent, $systemContent, "[$type] systemContent drifted from BlockDefaults");

            // Every key gets exactly the metadata its default value implies.
            foreach ($systemContent as $key => $value) {
                $expected = $this->expectedMetaFor((string) $key, $value);
                if ($expected === null) {
                    $this->assertArrayNotHasKey(
                        $key,
                        $arrayMeta,
                        "[$type] '$key' should be JSON-only but got a row editor"
                    );
                    continue;
                }

                $this->assertArrayHasKey($key, $arrayMeta, "[$type] '$key' is missing its row editor metadata");
                $actual = $arrayMeta[$key];
                $this->assertSame($expected['kind'], $actual['kind'] ?? null, "[$type] '$key' kind mismatch");

                if ($expected['kind'] === 'objects') {
                    $expFields = $expected['fields'];
                    $actFields = $actual['fields'] ?? [];
                    ksort($expFields);
                    ksort($actFields);
                    $this->assertSame($expFields, $actFields, "[$type] '$key' fields mismatch");
                    $sawObjects = true;
                } else {
                    $this->assertArrayNotHasKey('fields', $actual, "[$type] '$key' strings kind must not carry fields");
                    $sawStrings = true;
                }
            }

            // No phantom row editors for keys absent from system content.
            foreach (array_keys($arrayMeta) as $key) {
                $this->assertArrayHasKey($key, $systemContent, "[$type] row editor for unknown key '$key'");
            }
        }

        // The sweep must genuinely exercise both repeatable shapes.
        $this->assertTrue($sawObjects, 'Expected at least one array-of-objects row editor across all types');
        $this->assertTrue($sawStrings, 'Expected at least one array-of-strings row editor across all types');
    }

    public function test_known_types_expose_expected_row_editor_shapes(): void
    {
        $admin = $this->admin();

        // Array-of-objects: 'list' items carry text + icon string fields.
        $meta = $this->actingAs($admin, 'admin')
            ->get(route('admin.block-defaults.edit', 'list'))
            ->assertOk()
            ->viewData('arrayContentKeys');
        $this->assertSame('objects', $meta['items']['kind'] ?? null);
        $this->assertSame(['text' => 'string', 'icon' => 'string'], $meta['items']['fields'] ?? null);

        // Array-of-strings: 'ticker' items are a flat string list.
        $meta = $this->actingAs($admin, 'admin')
            ->get(route('admin.block-defaults.edit', 'ticker'))
            ->assertOk()
            ->viewData('arrayContentKeys');
        $this->assertSame(['kind' => 'strings'], $meta['items'] ?? null);
    }
}
