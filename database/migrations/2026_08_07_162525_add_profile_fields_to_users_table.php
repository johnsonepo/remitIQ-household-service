<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Display name shown publicly (e.g. next to a community-
            // submitted rate on Market Service) — not a login
            // credential, email remains the unique identifier.
            $table->string('username')->nullable()->unique()->after('name');

            $table->string('avatar_path')->nullable()->after('username');

            // Where the user is currently based — relevant context
            // when viewing a rate they posted (e.g. "posted from
            // Dubai" carries more trust/meaning than an anonymous ID).
            $table->string('country_code', 2)->nullable()->after('avatar_path');

            $table->text('bio')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'avatar_path', 'country_code', 'bio']);
        });
    }
};
