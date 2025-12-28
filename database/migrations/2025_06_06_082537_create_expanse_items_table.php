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
        Schema::create('expanse_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger("expanse_id");
            $table->unsignedInteger("expanse_type_id");
            $table->string("description");
            $table->integer("quantity");
            $table->double("unit_price");
            $table->double("sub_total");
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
            $table->foreign("expanse_id")->references("expanse_id")->on("expanse_masters")->onDelete("cascade");
            $table->foreign("expanse_type_id")->references("expanse_type_id")->on("expanse_types")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expanse_items');
    }
};
