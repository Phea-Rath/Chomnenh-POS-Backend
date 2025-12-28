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
        Schema::create('stock_details', function (Blueprint $table) {
            $table->id("detail_id");
            $table->unsignedBigInteger("stock_id");
            $table->unsignedBigInteger("item_id");
            $table->decimal("item_cost",8,2);
            $table->integer("quantity");
            $table->date("expire_date")->nullable();
            $table->date("transection_date");
            $table->boolean('is_waste')->default(false);
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
            $table->foreign("stock_id")->references("stock_id")->on("stock_masters")->onDelete("cascade");
            $table->foreign("item_id")->references("item_id")->on("items")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_details');
    }
};
