<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittances', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Who sent this (the tracking user, abroad).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Which household received it.
            $table->uuid('household_id');

            $table->foreignUuid('transfer_provider_id')->nullable()->constrained('transfer_providers')->nullOnDelete();

            $table->decimal('amount_sent', 15, 2);
            $table->string('sent_currency_code', 3);

            $table->decimal('amount_received', 15, 2);
            $table->string('received_currency_code', 3);

            // The exchange rate actually used for this transfer —
            // snapshotted at send time, since rates fluctuate and
            // this record must reflect what was true then, not
            // whatever Market Service reports now.
            $table->decimal('exchange_rate', 20, 10);

            // Optional link to which Market Service rate source this
            // was compared against, for informational purposes only
            // (no FK — Market Service owns that data, per the master
            // spec's data-ownership rules).
            $table->string('rate_source')->nullable();

            $table->date('sent_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('household_id')->references('id')->on('households')->cascadeOnDelete();
            $table->index(['user_id', 'sent_at']);
            $table->index(['household_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittances');
    }
};