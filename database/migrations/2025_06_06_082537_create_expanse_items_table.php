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
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger("expense_id");
            $table->unsignedInteger("expense_type_id");
            $table->string("description");
            $table->integer("quantity");
            $table->double("unit_price");
            $table->double("sub_total");
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
            $table->foreign("expense_id")->references("expense_id")->on("expense_masters")->onDelete("cascade");
            $table->foreign("expense_type_id")->references("expense_type_id")->on("expense_types")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
