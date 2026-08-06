<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('locale', 10)->default('en')->after('phone');
            $table->string('timezone', 50)->default('UTC')->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('timezone');
            $table->boolean('is_active')->default(true)->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'locale', 'timezone', 'last_login_at', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
