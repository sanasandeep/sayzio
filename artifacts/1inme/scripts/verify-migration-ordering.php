<?php

/**
 * Throwaway local verification for the MigrationOrderInspector detection logic.
 * The full PHPUnit Feature test can't run in the isolated dev env (its only
 * reachable DB is the shared RDS, which the destructive-DB guard refuses to let
 * the suite touch), so this standalone script boots the app and exercises the
 * recorder directly — no database, no phpunit. Not part of CI; delete freely.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Common\Support\MigrationOrderInspector;
use App\Modules\Common\Support\MigrationOrderRecorder;
use Illuminate\Support\Facades\DB;

$failures = 0;
$check = function (string $label, bool $ok) use (&$failures) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (! $ok) {
        $failures++;
    }
};

// Touch the inspector so the file (and the second class in it) is loaded.
class_exists(MigrationOrderInspector::class);

$connection = DB::connection();
// Initialise the default schema grammar on the connection (inspect() gets this
// for free by resolving app('db.schema'); here we construct Blueprints directly).
$connection->getSchemaBuilder();

// 1) Forward foreign key -> flagged on the referencing migration.
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('child_first');
$rec->create('child', function ($t) {
    $t->id();
    $t->foreignId('parent_id')->constrained('parent');
});
$rec->setCurrentMigration('parent_later');
$rec->create('parent', function ($t) {
    $t->id();
});
$v = $rec->violations();
$check('forward FK is detected', count($v) === 1 && $v[0]['type'] === 'foreign_key' && $v[0]['migration'] === 'child_first');

// 2) Correct order -> no violation.
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('parent_first');
$rec->create('parent', fn ($t) => $t->id());
$rec->setCurrentMigration('child_later');
$rec->create('child', function ($t) {
    $t->id();
    $t->foreignId('parent_id')->constrained('parent');
});
$check('correctly ordered FK is NOT flagged', $rec->violations() === []);

// 3) Self-referencing FK -> no violation.
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('tree');
$rec->create('nodes', function ($t) {
    $t->id();
    $t->foreignId('parent_id')->nullable()->constrained('nodes');
});
$check('self-referencing FK is NOT flagged', $rec->violations() === []);

// 4) Modify-before-create -> flagged.
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('alter_first');
$rec->table('widgets', fn ($t) => $t->string('label')->nullable());
$v = $rec->violations();
$check('modify-before-create is detected', count($v) === 1 && $v[0]['type'] === 'modify_before_create');

// 5) Write-to-not-yet-created-column -> flagged (recorder level).
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('create_target');
$rec->create('seedtarget', function ($t) {
    $t->id();
    $t->string('name');
});
$rec->setCurrentMigration('seed_flag');
$rec->inspectWriteQuery('insert into "seedtarget" ("name", "flag") values (?, ?)');
$v = $rec->violations();
$check(
    'write to not-yet-created column is detected',
    count($v) === 1 && $v[0]['type'] === 'write_column_before_create' && $v[0]['migration'] === 'seed_flag'
);

// 6) Write to an existing column -> not flagged (INSERT + UPDATE).
$rec = new MigrationOrderRecorder($connection);
$rec->setCurrentMigration('create_ok');
$rec->create('writeok', function ($t) {
    $t->id();
    $t->string('name');
    $t->boolean('flag');
});
$rec->setCurrentMigration('seed_ok');
$rec->inspectWriteQuery('insert into "writeok" ("name", "flag") values (?, ?)');
$rec->inspectWriteQuery('update "writeok" set "flag" = ? where "name" = ?');
$check('write to existing columns is NOT flagged', $rec->violations() === []);

// 7) End-to-end through inspect() under pretend, proving the pretend query log
//    is actually populated (guards against the check silently degrading to a
//    no-op). Point the migrator at a synthetic out-of-order trio.
$dir = storage_path('framework/testing/verify-ordering');
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$temp = [];
$writeMig = function (string $name, string $body) use ($dir, &$temp): string {
    $path = $dir . DIRECTORY_SEPARATOR . $name . '.php';
    file_put_contents($path, $body);
    $temp[] = $path;

    return $path;
};

$f1 = $writeMig('2099_09_01_000001_create_vseedtarget', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vseedtarget', function (Blueprint $t) { $t->id(); $t->string('name'); });
    }
};
PHP);
$f2 = $writeMig('2099_09_01_000002_seed_vflag', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void { DB::table('vseedtarget')->insert(['name' => 'x', 'vflag' => true]); }
};
PHP);
$f3 = $writeMig('2099_09_01_000003_add_vflag', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('vseedtarget', function (Blueprint $t) { $t->boolean('vflag')->default(false); });
    }
};
PHP);

app()->instance('migrator', Mockery::mock(Illuminate\Database\Migrations\Migrator::class, function ($mock) use ($f1, $f2, $f3) {
    $mock->shouldReceive('paths')->andReturn([]);
    $mock->shouldReceive('getMigrationFiles')->andReturn([
        '2099_09_01_000001_create_vseedtarget' => $f1,
        '2099_09_01_000002_seed_vflag'         => $f2,
        '2099_09_01_000003_add_vflag'          => $f3,
    ]);
}));

$e2e = MigrationOrderInspector::inspect();
$check('end-to-end inspect() detects the seeder write under pretend',
    $e2e['available'] === true
    && count($e2e['violations']) === 1
    && $e2e['violations'][0]['type'] === 'write_column_before_create'
    && $e2e['violations'][0]['migration'] === '2099_09_01_000002_seed_vflag');

foreach ($temp as $p) {
    @unlink($p);
}
app()->forgetInstance('migrator');

// 8) Real migration set replays clean.
$result = MigrationOrderInspector::inspect();
$check('real migration set is available', $result['available'] === true);
$check('real migration set has zero violations', $result['violations'] === []);

echo PHP_EOL . ($failures === 0 ? "ALL CHECKS PASSED" : "{$failures} CHECK(S) FAILED") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
