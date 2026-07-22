<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('country_code', 2)->default('TN');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            $table->unique(['holiday_date', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
