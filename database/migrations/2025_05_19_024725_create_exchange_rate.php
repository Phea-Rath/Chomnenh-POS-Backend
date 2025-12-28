<?php

use App\Models\ExchangeRate;
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
        Schema::create('exchange_rate', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_id')->primary();
            $table->decimal('usd_to_khr', 18, 2)->default(4000);
            $table->timestamps(); // creates created_at & updated_at with auto CURRENT_TIMESTAMP
            $table->foreign('profile_id')->references('id')->on('profiles');
        });

        ExchangeRate::insert([
            'usd_to_khr' => 4000,
            'profile_id' => 1
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate');
    }
};
