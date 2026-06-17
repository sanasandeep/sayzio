<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$budget = 70;
$start = time();

$migrator = $app->make('migrator');
$repo = $migrator->getRepository();

if (! $repo->repositoryExists()) {
    fwrite(STDERR, "migrations table missing; run migrate:install first\n");
    exit(2);
}

$paths = array_merge($migrator->paths(), [database_path('migrations')]);
$files = $migrator->getMigrationFiles($paths);

$ran = $repo->getRan();
$pendingNames = array_values(array_diff(array_keys($files), $ran));

echo "pending: ".count($pendingNames)."\n";

$idempotent = ['42P07', '42P01', '42704', '42710', '42701', '23505', '0A000'];

$done = 0; $orphaned = 0; $ranOk = 0;

foreach ($pendingNames as $name) {
    if (time() - $start > $budget) { echo "time budget hit\n"; break; }

    $file = $files[$name];
    try {
        $migration = require $file;
        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up();
            $ranOk++;
        }
        $batch = ($repo->getLastBatchNumber() ?: 0) + 1;
        $repo->log($name, $batch);
        $done++;
    } catch (\Throwable $e) {
        $sqlstate = '';
        if (isset($e->errorInfo[0])) $sqlstate = $e->errorInfo[0];
        $msg = $e->getMessage();
        $isIdem = false;
        foreach ($idempotent as $code) {
            if ($sqlstate === $code || str_contains($msg, $code)) { $isIdem = true; break; }
        }
        if ($isIdem) {
            try {
                $batch = ($repo->getLastBatchNumber() ?: 0) + 1;
                $repo->log($name, $batch);
                $orphaned++; $done++;
            } catch (\Throwable $e2) {
                fwrite(STDERR, "log-orphan failed for $name: ".$e2->getMessage()."\n");
                exit(3);
            }
        } else {
            fwrite(STDERR, "ABORT on $name [$sqlstate]: $msg\n");
            exit(4);
        }
    }
}

echo "done=$done ran=$ranOk orphaned=$orphaned remaining=".(count($pendingNames)-$done)."\n";
