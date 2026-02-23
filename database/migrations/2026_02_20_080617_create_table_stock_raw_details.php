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
        Schema::create('stock_raw_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id');
            $table->unsignedBigInteger('raw_material_id');
            $table->decimal('item_cost', 12, 2)->default(0);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->date('expire_date')->nullable();
            $table->dateTime('transection_date')->nullable();
            $table->boolean('is_waste')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->foreign('stock_id')->references('stock_id')->on('stock_masters')->onDelete('cascade');
            $table->foreign('raw_material_id')->references('id')->on('raw_materials');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_raw_details');
    }
};
