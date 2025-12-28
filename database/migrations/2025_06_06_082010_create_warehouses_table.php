<?php

use App\Models\Warehouses;
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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->increments("warehouse_id");
            $table->string("warehouse_name");
            $table->string("status");
            $table->integer("created_by");
            $table->boolean("is_deleted")->default(0);
            $table->timestamps();
        });

        Warehouses::insert([
            [
                'warehouse_name' => 'main stock',
                'status' => 'stock',
                'created_by' => 1
            ],
            [
                'warehouse_name' => 'PO',
                'status' => 'stock',
                'created_by' => 1
            ],
            [
                'warehouse_name' => 'sale stock',
                'status' => 'stock',
                'created_by' => 1
            ],
            [
                'warehouse_name' => 'waste stock',
                'status' => 'stock',
                'created_by' => 1
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('werehouses');
    }
};
