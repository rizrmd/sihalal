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
            // Add new payload column (JSON)
            $table->json('payload')->nullable()->after('form_id');

            // Drop old columns that are now in payload
            $table->dropColumn(['nama_lengkap', 'email', 'nama_sppg', 'alamat_sppg', 'synced_at', 'created_at_jotform']);
        });

        // Make submission_id and form_id not nullable (if table is empty or you want to enforce)
        // Note: This will fail if there are existing records with null values
        // Schema::table('jotform_syncs', function (Blueprint $table) {
        //     $table->string('submission_id')->nullable(false)->change();
        //     $table->string('form_id')->nullable(false)->change();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jotform_syncs', function (Blueprint $table) {
            // Drop payload column
            $table->dropColumn('payload');

            // Restore old columns
            $table->string('nama_lengkap')->nullable()->after('form_id');
            $table->string('email')->nullable()->after('nama_lengkap');
            $table->string('nama_sppg')->nullable()->after('email');
            $table->text('alamat_sppg')->nullable()->after('nama_sppg');
            $table->timestamp('synced_at')->nullable()->after('status_submit');
            $table->timestamp('created_at_jotform')->nullable()->after('synced_at');

            // Add indexes
            $table->index('nama_lengkap');
            $table->index('email');
        });
    }
};
