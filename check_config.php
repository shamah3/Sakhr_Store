<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "FLOOSAK_BYPASS: " . (config('floosak.bypass') ? 'TRUE' : 'FALSE') . "\n";
echo "FLOOSAK_ENABLED: " . (config('floosak.enabled') ? 'TRUE' : 'FALSE') . "\n";
