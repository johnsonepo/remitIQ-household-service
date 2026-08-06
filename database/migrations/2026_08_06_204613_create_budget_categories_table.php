<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable user_id: null = a default/system category
            // (Food, Rent, etc.) available to everyone; non-null =
            // a custom category a specific user created for themselves.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable(); // hex color for UI
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_categories');
    }
};
