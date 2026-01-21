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
        Schema::create('si_halal_configurations', function (Blueprint $table) {
            $table->id();
            $table->text('api_key')->nullable();
            $table->text('form_id')->nullable();
            $table->text('bearer_token')->nullable();
            $table->text('pelaku_usaha_uuid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('si_halal_configurations');
    }
};
