<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('remittance_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->foreign('remittance_id')->references('id')->on('remittances')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_attachments');
    }
};
