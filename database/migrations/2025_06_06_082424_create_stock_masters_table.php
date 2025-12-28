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
        Schema::create('stock_masters', function (Blueprint $table) {
            $table->id("stock_id");
            $table->string("stock_no");
            $table->unsignedInteger("stock_type_id");  // must match referenced type
            $table->unsignedInteger("from_warehouse"); // must match referenced type
            $table->unsignedInteger("warehouse_id");   // make it consistent too
            $table->unsignedInteger("order_id")->nullable();   // make it consistent too
            $table->date("stock_date");
            $table->string("stock_remark");
            $table->unsignedInteger("stock_created_by");
            $table->integer("id_transfer")->default(0);
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();

            $table->foreign("stock_type_id")->references("stock_type_id")->on("stock_types")->onDelete('cascade');
            $table->foreign("from_warehouse")->references("warehouse_id")->on("warehouses")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_masters');
    }
};
