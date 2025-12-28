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
        Schema::create('attribute_value_details', function (Blueprint $table) {
            $table->unsignedBigInteger('attribute_detail_id');
            $table->unsignedBigInteger('attribute_value_id');
            $table->timestamps();
            $table->foreign('attribute_detail_id')->references('id')->on('attribute_details')->onDelete('cascade');
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_value_details');
    }
};
