<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Clearing Cache...<br>";
$kernel->call('cache:clear');
$kernel->call('route:clear');
$kernel->call('config:clear');
$kernel->call('view:clear');

echo "Purging OPcache...<br>";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully.<br>";
}

echo "Done!";
