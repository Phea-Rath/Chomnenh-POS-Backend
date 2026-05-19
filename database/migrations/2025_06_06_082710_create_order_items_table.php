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
            $table->decimal("price", 20, 2);
            $table->decimal("item_price", 20, 2);
            $table->integer("discount")->default(0);
            $table->enum('item_for', ['sale', 'sample', 'free'])->default('sale');
            $table->integer("quantity");
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
