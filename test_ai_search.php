<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use LaravelAIAgent\Facades\Agent;

echo "Testing Smart Search Agent...\n";
try {
    $response = Agent::make('SmartSearch')
        ->system('أنت محرك بحث ذكي لتطبيق "متجري". أجب باختصار من خلال الأدوات المتاحة.')
        ->chat('ماهي الخصومات المتوفرة حاليا؟');
    
    echo "Response:\n";
    echo $response . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
