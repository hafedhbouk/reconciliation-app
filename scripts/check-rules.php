<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rules = App\Models\MatchingRule::all(['id', 'name', 'source_a_id', 'source_b_id', 'is_active']);
foreach ($rules as $rule) {
    echo $rule->id . ': ' . $rule->name . ' (' . $rule->source_a_id . ' -> ' . $rule->source_b_id . ') active=' . ($rule->is_active ? 'yes' : 'no') . "\n";
}
echo 'Total: ' . $rules->count() . "\n";
