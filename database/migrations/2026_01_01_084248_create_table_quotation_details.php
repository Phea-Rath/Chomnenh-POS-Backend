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
        Schema::create('quotation_details', function (Blueprint $table) {
            $table->id('detail_id'); // int unsigned auto increment

            $table->unsignedBigInteger('quotation_id');

            $table->unsignedBigInteger('item_id');

            $table->string('item_name', 255)
                  ->comment('Stores item name at time of quotation');

            $table->decimal('quantity', 10, 2);

            $table->decimal('price', 10, 2);

            $table->decimal('discount', 10, 2)
                  ->default(0.00)
                  ->comment('Discount percentage');

            $table->decimal('total_price', 10, 2)
                  ->comment('Calculated total after discount');

            $table->string('scale', 50)
                  ->nullable()
                  ->comment('ខ្នាត');

            $table->timestamp('created_at')
                  ->useCurrent();

            // Optional: Foreign Keys
            $table->foreign('quotation_id')->references('quotation_id')->on('quotations')->onDelete('cascade');
            $table->foreign('item_id')->references('item_id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_quotation_details');
    }
};
