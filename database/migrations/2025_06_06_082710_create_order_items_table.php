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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("order_id");
            $table->unsignedBigInteger("item_id");
            $table->string("item_name");
            $table->double("price");
            $table->double("item_price");
            $table->integer("discount")->default(0);
            $table->decimal('exchange_rate', 8, 2);
            $table->decimal('item_cost', 8, 2);
            $table->decimal('item_wholesale_price', 8, 2);
            $table->integer("quantity");
            $table->integer("status")->default(1);
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
            $table->foreign("order_id")->references("order_id")->on("order_masters")->onDelete("cascade");
            $table->foreign("item_id")->references("item_id")->on("items")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
