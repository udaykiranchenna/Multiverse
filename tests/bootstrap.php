<?php

declare(strict_types=1);

$autoload = getenv('MULTIVERSE_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'MadeItEasyTools\\Multiverse\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $directory = str_starts_with($relative, 'Tests\\') ? 'tests' : 'src';
    $relative = $directory === 'tests' ? substr($relative, 6) : $relative;
    $path = dirname(__DIR__).'/'.$directory.'/'.str_replace('\\', '/', $relative).'.php';

    if (is_file($path)) {
        require $path;
    }
}, true, true);
