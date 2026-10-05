<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->string('batch_reference', 36)->nullable()->index();
            $table->boolean('is_expected')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->dropIndex(['batch_reference']);
            $table->dropColumn(['batch_reference', 'is_expected']);
        });
    }
};