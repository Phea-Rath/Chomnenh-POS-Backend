<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Deliver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivers', function (Blueprint $table) {
            $table->increments('deliver_id');
            $table->string('deliver_name');
            $table->string('image')->nullable();
            $table->integer('created_by');
            $table->timestamps();
        });

        Deliver::create([
            'deliver_name' => 'Unknown',
            'image' => null,
            'created_by' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_deliver');
    }
};
