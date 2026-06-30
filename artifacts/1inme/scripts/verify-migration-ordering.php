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

// 5) Real migration set replays clean.
$result = MigrationOrderInspector::inspect();
$check('real migration set is available', $result['available'] === true);
$check('real migration set has zero violations', $result['violations'] === []);

echo PHP_EOL . ($failures === 0 ? "ALL CHECKS PASSED" : "{$failures} CHECK(S) FAILED") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
