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
        Schema::create('items', function (Blueprint $table) {
            $table->id("item_id");
            $table->string("item_code");
            $table->string("barcode");
            $table->string("item_name");
            $table->double("item_cost");
            $table->double("item_price");
            $table->double("wholesale_price");
            $table->integer("discount")->default(0);
            $table->unsignedInteger("scale_id");
            $table->unsignedInteger("category_id");
            $table->string("item_type")->nullable();
            $table->unsignedInteger("brand_id");
            $table->integer("created_by");
            $table->integer("stock_in")->default(0);
            $table->integer("stock_out")->default(0);
            $table->integer("wasted")->default(0);
            $table->integer("sold")->default(0);
            $table->integer("reviews")->default(0);
            $table->decimal("rating",3,2)->default(0);

            // $table->string("item_image")->nullable();
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
            $table->foreign("scale_id")->references('scale_id')->on("scales")->onDelete('cascade');
            $table->foreign("category_id")->references('category_id')->on("categories")->onDelete('cascade');
            $table->foreign("brand_id")->references('brand_id')->on("brands")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
