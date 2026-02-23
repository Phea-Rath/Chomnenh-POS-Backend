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
        Schema::create('order_masters', function (Blueprint $table) {
            $table->id("order_id");
            $table->string("order_no");
            $table->string("order_tel")->nullable();
            $table->string("order_address")->nullable();
            $table->date("order_date");
            $table->double("delivery_fee")->default(0);
            $table->unsignedInteger("deliver_id")->nullable();
            $table->integer("through")->nullable();
            $table->double("order_subtotal");
            $table->double("order_discount");
            $table->string("sale_type");//'sale, wholesale'
            $table->unsignedInteger("order_customer_id")->default(0);
            $table->string("order_payment_method");
            $table->string("order_payment_status");
            $table->double("order_total");
            $table->double("order_tax")->default(0);
            $table->double("exchange_rate")->default(4000);
            $table->double("balance");
            $table->double("payment");
            $table->unsignedBigInteger("created_by");
            $table->integer("status")->default(1);
                // 1. pending
                // 2. editing
                // 3. packaged
                // 4. pickup
                // 5. delivering
                // 6. completed
                // 7. cancelled
            $table->boolean("is_active")->default(true);
            $table->boolean("is_deleted")->default(0);
            $table->boolean("online")->default(0);
            $table->timestamps();
            $table->foreign("created_by")->references("id")->on("users");
            $table->foreign("order_customer_id")->references("customer_id")->on("customers");
            $table->foreign("deliver_id")->references("deliver_id")->on("delivers");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_masters');
    }
};
