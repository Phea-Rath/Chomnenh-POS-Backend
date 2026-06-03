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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_no')->unique();
            $table->dateTime('production_date');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2);
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->decimal('exchange_rate', 15, 2)->default(4000);
            $table->integer('created_by');
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
            $table->foreign('item_id')->references('item_id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
