<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->date('transaction_date')->nullable();
            $table->dateTime('transaction_datetime')->nullable();
            $table->bigInteger('amount_millimes')->nullable();
            $table->string('canal')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            $table->index(['source_id', 'external_reference']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
