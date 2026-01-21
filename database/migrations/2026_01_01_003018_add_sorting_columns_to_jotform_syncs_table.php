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
        Schema::table('jotform_syncs', function (Blueprint $table) {
            $table->string('nama_lengkap')->nullable()->after('status_submit');
            $table->string('email')->nullable()->after('nama_lengkap');
            $table->string('nama_sppg')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jotform_syncs', function (Blueprint $table) {
            $table->dropColumn(['nama_lengkap', 'email', 'nama_sppg']);
        });
    }
};
