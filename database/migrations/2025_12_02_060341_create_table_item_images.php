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
        Schema::create('item_images', function (Blueprint $table) {
            $table->unsignedBigInteger("item_id");
            $table->unsignedBigInteger("image_id");
            $table->primary(['item_id', 'image_id']);
            $table->timestamps();
            $table->foreign("item_id")->references('item_id')->on("items")->onDelete('cascade');
            $table->foreign("image_id")->references('id')->on("images")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_images');
    }
};
