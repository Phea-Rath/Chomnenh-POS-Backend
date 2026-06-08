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
        Schema::table('permission', function (Blueprint $table) {
            $table->boolean('is_view')->default(false);
            $table->boolean('is_modify')->default(false);
            $table->boolean('is_drop')->default(false);
            $table->boolean('is_execute')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permission', function (Blueprint $table) {
            $table->dropColumn(['is_view', 'is_modify', 'is_drop', 'is_execute']);
        });
    }
};
