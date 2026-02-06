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
        Schema::create('production_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2);
            $table->decimal('cost_per_unit', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->integer('created_by');
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('item_id')->references('item_id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_details');
    }
};
