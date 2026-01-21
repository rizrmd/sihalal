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
            $table->string('submission_id')->nullable()->unique()->after('id');
            $table->string('form_id')->nullable()->after('submission_id');
            $table->timestamp('created_at_jotform')->nullable()->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jotform_syncs', function (Blueprint $table) {
            $table->dropColumn(['submission_id', 'form_id', 'created_at_jotform']);
        });
    }
};
