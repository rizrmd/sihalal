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
            $table->uuid('pabrik_id')->nullable()->after('reg_id');
            $table->json('data_pengajuan')->nullable()->after('pabrik_id');
            $table->json('komitmen_tanggung_jawab')->nullable()->after('data_pengajuan');
            $table->json('bahan')->nullable()->after('komitmen_tanggung_jawab');
            $table->json('proses')->nullable()->after('bahan');
            $table->json('produk')->nullable()->after('proses');
            $table->json('pemantauan_evaluasi')->nullable()->after('produk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jotform_syncs', function (Blueprint $table) {
            $table->dropColumn([
                'pabrik_id',
                'data_pengajuan',
                'komitmen_tanggung_jawab',
                'bahan',
                'proses',
                'produk',
                'pemantauan_evaluasi',
            ]);
        });
    }
};
