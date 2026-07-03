<?php

namespace Tests\Feature;

use App\Modules\Common\Support\MigrationOrderInspector;
use Illuminate\Database\Migrations\Migrator;
use Tests\TestCase;

/**
 * Guards against broken migration *ordering* — a migration that modifies or
 * foreign-keys a table only created by a LATER-dated migration. That class of
 * bug builds fine on any database that already has the table (the shared
 * dev/live RDS, where migrations were applied incrementally) but breaks
 * `migrate:fresh` from an empty database. It is exactly what Task #2989 had to
 * fix and what slipped through because no automated check ever rebuilt the
 * schema from zero.
 *
 * Two complementary assertions:
 *  - The real migration set replays cleanly from an empty schema (the
 *    regression guard — this is what fails CI when someone adds an out-of-order
 *    migration).
 *  - The inspector actually DETECTS forward references, so it can never silently
 *    degrade into a no-op that always passes.
 *
 * No database is needed: {@see MigrationOrderInspector} replays under
 * `Connection::pretend()` with the `Schema` facade swapped for an in-memory
 * recorder, so these tests neither read nor write any table.
 */
class MigrationOrderingTest extends TestCase
{
    public function test_real_migration_set_replays_cleanly_from_empty_schema(): void
    {
        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available'], 'the ordering inspector should be able to run: ' . ($result['error'] ?? ''));
        $this->assertGreaterThan(0, $result['scanned'], 'at least one migration should have been replayed');

        $this->assertSame(
            [],
            $result['violations'],
            'broken migration ordering detected — a migration references a table created only by a later '
            . "migration, which would break a fresh build from an empty database:\n"
            . $this->describe($result['violations'])
        );
    }

    public function test_detects_foreign_key_to_a_later_created_table(): void
    {
        // Earlier migration creates a child table with a foreign key to a parent
        // table that is only created by the later migration.
        $earlier = $this->writeMigration('2099_01_01_000001_create_ordering_child', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_child', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ordering_parent_id')->constrained('ordering_parent');
        });
    }
};
PHP);

        $later = $this->writeMigration('2099_01_01_000002_create_ordering_parent', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_parent', function (Blueprint $t) {
            $t->id();
        });
    }
};
PHP);

        $this->mockMigrator([
            '2099_01_01_000001_create_ordering_child'  => $earlier,
            '2099_01_01_000002_create_ordering_parent' => $later,
        ]);

        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available']);
        $this->assertNotEmpty($result['violations'], 'a forward foreign-key reference should be detected');

        $violation = $result['violations'][0];
        $this->assertSame('2099_01_01_000001_create_ordering_child', $violation['migration']);
        $this->assertSame('foreign_key', $violation['type']);
        $this->assertStringContainsString('ordering_parent', $violation['message']);
    }

    public function test_detects_modifying_a_table_before_it_is_created(): void
    {
        $earlier = $this->writeMigration('2099_02_01_000001_alter_ordering_widget', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('ordering_widget', function (Blueprint $t) {
            $t->string('label')->nullable();
        });
    }
};
PHP);

        $later = $this->writeMigration('2099_02_01_000002_create_ordering_widget', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_widget', function (Blueprint $t) {
            $t->id();
        });
    }
};
PHP);

        $this->mockMigrator([
            '2099_02_01_000001_alter_ordering_widget'  => $earlier,
            '2099_02_01_000002_create_ordering_widget' => $later,
        ]);

        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available']);
        $this->assertNotEmpty($result['violations'], 'modifying a not-yet-created table should be detected');
        $this->assertSame('2099_02_01_000001_alter_ordering_widget', $result['violations'][0]['migration']);
        $this->assertSame('modify_before_create', $result['violations'][0]['type']);
    }

    public function test_detects_write_to_a_column_before_it_is_created(): void
    {
        // A table is created, then an EARLIER-than-the-add-column migration
        // (typically via a seeder it runs) writes a column that only a LATER
        // migration adds — exactly the `is_internal` bug that broke
        // `migrate:fresh`. The write is invisible to the schema-only checks
        // because it lives in data, not a Blueprint.
        $create = $this->writeMigration('2099_04_01_000001_create_ordering_seedtarget', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_seedtarget', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
    }
};
PHP);

        $seed = $this->writeMigration('2099_04_01_000002_seed_ordering_flag', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        // Writes `flag`, which is only added by the later migration below.
        DB::table('ordering_seedtarget')->insert(['name' => 'x', 'flag' => true]);
    }
};
PHP);

        $addColumn = $this->writeMigration('2099_04_01_000003_add_flag_to_ordering_seedtarget', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('ordering_seedtarget', function (Blueprint $t) {
            $t->boolean('flag')->default(false);
        });
    }
};
PHP);

        $this->mockMigrator([
            '2099_04_01_000001_create_ordering_seedtarget'          => $create,
            '2099_04_01_000002_seed_ordering_flag'                  => $seed,
            '2099_04_01_000003_add_flag_to_ordering_seedtarget'     => $addColumn,
        ]);

        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available']);
        $this->assertNotEmpty($result['violations'], 'writing a not-yet-created column should be detected');

        $violation = $result['violations'][0];
        $this->assertSame('2099_04_01_000002_seed_ordering_flag', $violation['migration']);
        $this->assertSame('write_column_before_create', $violation['type']);
        $this->assertStringContainsString('flag', $violation['message']);
    }

    public function test_write_to_an_existing_column_is_not_flagged(): void
    {
        // Mirror of the test above: when the column already exists, a data write
        // to it must not be flagged (no false positive).
        $create = $this->writeMigration('2099_05_01_000001_create_ordering_writeok', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_writeok', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('flag')->default(false);
        });
    }
};
PHP);

        $seed = $this->writeMigration('2099_05_01_000002_seed_ordering_writeok', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::table('ordering_writeok')->insert(['name' => 'x', 'flag' => true]);
        DB::table('ordering_writeok')->where('name', 'x')->update(['flag' => false]);
    }
};
PHP);

        $this->mockMigrator([
            '2099_05_01_000001_create_ordering_writeok' => $create,
            '2099_05_01_000002_seed_ordering_writeok'   => $seed,
        ]);

        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available']);
        $this->assertSame([], $result['violations'], 'a write to an existing column must not be flagged');
    }

    public function test_correct_order_produces_no_violation(): void
    {
        // The mirror of the foreign-key test: when the parent is created first,
        // the inspector must stay silent (no false positive).
        $first = $this->writeMigration('2099_03_01_000001_create_ordering_parent_ok', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_parent_ok', function (Blueprint $t) {
            $t->id();
        });
    }
};
PHP);

        $second = $this->writeMigration('2099_03_01_000002_create_ordering_child_ok', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ordering_child_ok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ordering_parent_ok_id')->constrained('ordering_parent_ok');
        });
    }
};
PHP);

        $this->mockMigrator([
            '2099_03_01_000001_create_ordering_parent_ok' => $first,
            '2099_03_01_000002_create_ordering_child_ok'  => $second,
        ]);

        $result = MigrationOrderInspector::inspect();

        $this->assertTrue($result['available']);
        $this->assertSame([], $result['violations'], 'correctly-ordered migrations must not be flagged');
    }

    /** @var array<int,string> absolute paths of temp migration files to clean up */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function writeMigration(string $name, string $contents): string
    {
        $dir = storage_path('framework/testing/migration-ordering');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name . '.php';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Point the inspector at exactly the given migration files (name => path),
     * excluding the real migration set, so a synthetic out-of-order pair can be
     * exercised in isolation. Mirrors the migrator-mocking approach in
     * {@see SchemaManifestDriftTest}.
     *
     * @param array<string,string> $files
     */
    private function mockMigrator(array $files): void
    {
        $this->app->instance('migrator', \Mockery::mock(Migrator::class, function ($mock) use ($files) {
            $mock->shouldReceive('paths')->andReturn([]);
            $mock->shouldReceive('getMigrationFiles')->andReturn($files);
        }));
    }

    /**
     * @param array<int,array{migration:string,type:string,message:string}> $violations
     */
    private function describe(array $violations): string
    {
        return collect($violations)
            ->map(fn ($v) => "  - {$v['migration']} [{$v['type']}]: {$v['message']}")
            ->implode("\n");
    }
}
