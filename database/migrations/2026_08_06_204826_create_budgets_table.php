<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The sender tracking this budget (a user abroad).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Which household this budget supports.
            $table->uuid('household_id');

            // Calendar month this budget applies to, stored as the
            // first day of the month for easy range queries.
            $table->date('month');

            $table->string('currency_code', 3)->default('XAF');
            $table->decimal('total_planned', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('household_id')->references('id')->on('households')->cascadeOnDelete();

            // One budget per user, per household, per month.
            $table->unique(['user_id', 'household_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};