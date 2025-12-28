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
        Schema::table('expanse_masters', function (Blueprint $table) {
             Schema::create('expanse_masters', function (Blueprint $table) {
            $table->increments("expanse_id");
            $table->string("expanse_no");
            $table->date("expanse_date");
            $table->string("expanse_by");
            $table->double("amount");
            $table->integer("created_by");
            $table->string("expanse_other");
            $table->string("expanse_supplier");
            $table->boolean("is_active")->default(true);
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
        });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expanse_masters');
    }

};
