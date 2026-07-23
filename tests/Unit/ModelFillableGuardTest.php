<?php

/**
 * Regression guard for the Phase 5 security audit's "no mass-assignment
 * gap" finding: every model under app/Models/ (not app/Models/Concerns/,
 * which holds traits, not models) must declare a non-empty $fillable and
 * never $guarded = []. Plain reflection -- no new Pest arch-testing
 * package needed for a single check.
 */
test('every model declares a non-empty fillable array and never an empty guarded array', function () {
    $files = glob(app_path('Models/*.php'));
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        expect(class_exists($class))->toBeTrue("Expected {$class} to exist.");

        $reflection = new ReflectionClass($class);
        expect($reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class))->toBeTrue();

        $model = $reflection->newInstanceWithoutConstructor();

        $fillable = $model->getFillable();
        expect($fillable)->not->toBeEmpty("{$class} must declare a non-empty \$fillable array.");

        $guarded = $model->getGuarded();
        expect($guarded)->not->toBe([], "{$class} must not use \$guarded = [] (disables mass-assignment protection entirely).");
    }
});
