<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('household_id');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('household_id')->references('id')->on('households')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
