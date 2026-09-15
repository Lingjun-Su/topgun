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
        Schema::table('third_channels', function (Blueprint $table) {
            $table->integer('push_timeout')->nullable()->default(10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('third_channels', function (Blueprint $table) {
            $table->integer('push_timeout')->default(10)->change();
        });
    }
};
