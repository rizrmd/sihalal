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
            $table->uuid('reg_id')->nullable()->after('id');
            $table->text('notes')->nullable()->after('status_submit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jotform_syncs', function (Blueprint $table) {
            $table->dropColumn(['reg_id', 'notes']);
        });
    }
};
