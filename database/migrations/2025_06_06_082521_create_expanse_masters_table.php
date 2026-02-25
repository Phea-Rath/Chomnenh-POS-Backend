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
        Schema::table('expense_masters', function (Blueprint $table) {
             Schema::create('expense_masters', function (Blueprint $table) {
            $table->increments("expense_id");
            $table->string("expense_no");
            $table->date("expense_date");
            $table->string("expense_by");
            $table->double("amount");
            $table->integer("created_by");
            $table->string("expense_other");
            $table->string("expense_supplier");
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
        Schema::dropIfExists('expense_masters');
    }

};
