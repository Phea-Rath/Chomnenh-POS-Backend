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
        Schema::create('expense_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('expense_id');
            $table->unsignedBigInteger('image_id');
            $table->timestamps();
            $table->foreign('expense_id')->references('expense_id')->on('expense_masters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_images');
    }
};
