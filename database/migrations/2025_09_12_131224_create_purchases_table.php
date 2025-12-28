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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id('purchase_id');
            $table->string('purchase_no', 50)->nullable();
            $table->unsignedInteger('supplier_id');
            $table->date('purchase_date')->nullable();
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->tinyInteger('is_deleted')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->decimal('exchange_rate', 10, 2)->default(1);
            $table->tinyInteger('status')->default(0);
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
