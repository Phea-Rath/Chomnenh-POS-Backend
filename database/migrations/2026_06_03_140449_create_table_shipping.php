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
        Schema::create('shipping', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('fee')->default('0');
            $table->enum('vai', ['truck', 'air', 'sea'])->default('truck');
            $table->text('remark')->nullable();
            $table->string('term', 100)->nullable();
            $table->date('date')->nullable();
            $table->integer('created_by');
            $table->timestamps();
            $table->foreign('purchase_id')->references('purchase_id')->on('purchases')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping');
    }
};
