<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_exports', function (Blueprint $table) {
            $table->string('type')->default('matching')->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('matching_exports', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};