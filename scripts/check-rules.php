<?php

use App\Models\MatchingRule;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$rules = MatchingRule::all(['id', 'name', 'source_a_id', 'source_b_id', 'is_active']);
foreach ($rules as $rule) {
    echo $rule->id.': '.$rule->name.' ('.$rule->source_a_id.' -> '.$rule->source_b_id.') active='.($rule->is_active ? 'yes' : 'no')."\n";
}
echo 'Total: '.$rules->count()."\n";
