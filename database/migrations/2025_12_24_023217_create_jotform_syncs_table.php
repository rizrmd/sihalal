<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jotform_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('nama_lengkap')->index();
            $table->string('email')->index();
            $table->string('nama_sppg');
            $table->text('alamat_sppg');
            $table->string('status_submit')->index();
            $table->timestamp('synced_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jotform_syncs');
    }
};
