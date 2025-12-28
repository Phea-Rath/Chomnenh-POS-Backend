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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('supplier_id');
            $table->string('supplier_name');
            $table->string('supplier_address');
            $table->string('communes')->nullable();
            $table->string('districts')->nullable();
            $table->string('provinces')->nullable();
            $table->string('villages')->nullable();
            $table->string('commune_id')->nullable();
            $table->string('district_id')->nullable();
            $table->string('province_id')->nullable();
            $table->string('village_id')->nullable();
            $table->string('supplier_tel')->nullable();
            $table->string('supplier_email')->nullable();
            $table->integer('created_by');
            $table->string('image')->nullable();
            $table->string('is_deleted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
