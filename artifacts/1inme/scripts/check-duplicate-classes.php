<?php

/**
 * No two files in app/ may declare the same class, and no file may sit in
 * a directory its namespace does not match.
 *
 * ---- Why this exists ---------------------------------------------------
 *
 * On 2026-10-05 a careless `cp` of a backup directory put eleven stray
 * files into app/Services/AI/Editor/ -- duplicate copies of five real
 * classes, plus eight Blade templates, in a folder that has nothing to do
 * with any of them. They were committed and merged before anyone noticed,
 * because nothing looks at them: PSR-4 resolves by PATH, so a file whose
 * namespace does not match its folder is simply never loaded.
 *
 * That is exactly what makes it dangerous. The copies sat there inert,
 * one of them already diverged from the real file, waiting for the first
 * person to run `composer dump-autoload --optimize` -- which builds a
 * classmap by SCANNING rather than by path, picks one of the two
 * definitions, and can silently serve a months-old version of a class
 * that the repository appears to have fixed.
 *
 * A file nothing loads is not harmless. It is a bug with a delay on it.
 *
 * Run: php scripts/check-duplicate-classes.php
 */

$root = dirname(__DIR__);
$appDir = $root.'/app';

/** app/Foo/Bar.php => App\Foo */
function expectedNamespace(string $file, string $appDir): string
{
    $rel = trim(str_replace($appDir, '', dirname($file)), '/');

    return $rel === '' ? 'App' : 'App\\'.str_replace('/', '\\', $rel);
}

$seen      = [];
$duplicates = [];
$misplaced  = [];
$strays     = [];
$count      = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();

    // Anything that is not PHP has no business in app/ at all. Eight Blade
    // templates rode in on the same mistake and would never have rendered
    // from here.
    if ($file->getExtension() !== 'php') {
        $strays[] = str_replace($root.'/', '', $path);
        continue;
    }

    $count++;
    $src = file_get_contents($path);

    if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)) {
        continue;
    }
    $namespace = trim($ns[1]);

    $expected = expectedNamespace($path, $appDir);
    if ($namespace !== $expected) {
        $misplaced[] = sprintf(
            "%s\n     declares  %s\n     but sits in a folder that means  %s",
            str_replace($root.'/', '', $path),
            $namespace,
            $expected
        );
    }

    if (! preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $src, $cls)) {
        continue;
    }

    $fqcn = $namespace.'\\'.$cls[1];

    if (isset($seen[$fqcn])) {
        $duplicates[] = sprintf(
            "%s\n     %s\n     %s",
            $fqcn,
            str_replace($root.'/', '', $seen[$fqcn]),
            str_replace($root.'/', '', $path)
        );
        continue;
    }

    $seen[$fqcn] = $path;
}

$problems = 0;

if ($duplicates) {
    $problems += count($duplicates);
    echo "\n✗ Two files declare the same class. `composer dump-autoload --optimize`\n";
    echo "  builds its classmap by scanning, so it will pick ONE of these -- possibly\n";
    echo "  the stale one -- and silently serve it everywhere:\n\n";
    foreach ($duplicates as $d) {
        echo '   '.$d."\n\n";
    }
}

if ($misplaced) {
    $problems += count($misplaced);
    echo "\n✗ A file's namespace does not match its folder, so PSR-4 never loads it.\n";
    echo "  It looks like working code and is dead:\n\n";
    foreach ($misplaced as $m) {
        echo '   '.$m."\n\n";
    }
}

if ($strays) {
    $problems += count($strays);
    echo "\n✗ Not PHP, and in app/. Nothing here is ever read:\n\n";
    foreach ($strays as $s) {
        echo '   '.$s."\n";
    }
    echo "\n";
}

if ($problems > 0) {
    echo "check-duplicate-classes: FAILED ({$problems} problem(s))\n";
    exit(1);
}

echo "check-duplicate-classes: ok — {$count} PHP file(s) in app/, each class declared once, each in the folder its namespace names.\n";
exit(0);
