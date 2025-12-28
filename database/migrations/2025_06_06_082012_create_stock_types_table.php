<?php

use App\Models\StockTypes;
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
        Schema::create('stock_types', function (Blueprint $table) {
            $table->increments("stock_type_id");
            $table->string("stock_type_name");
            $table->integer("created_by");
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
        });
        StockTypes::insert([
            ['stock_type_name' => 'stock return', 'created_by' => 1],
            ['stock_type_name' => 'stock in', 'created_by' => 1],
            ['stock_type_name' => 'stock out', 'created_by' => 1],
            ['stock_type_name' => 'stock waste', 'created_by' => 1],
            ['stock_type_name' => 'stock sale', 'created_by' => 1],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_types');
    }
};
