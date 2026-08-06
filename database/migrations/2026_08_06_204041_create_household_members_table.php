<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('household_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Household-scoped role — a user can be 'owner' of one
            // household and 'member' of another. Not a global role.
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');

            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->foreign('household_id')->references('id')->on('households')->cascadeOnDelete();
            $table->unique(['household_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};