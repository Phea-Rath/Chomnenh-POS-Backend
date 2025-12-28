<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Customers;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('customer_id');
            $table->string('customer_name');
            $table->string('customer_email')->unique()->nullable();
            $table->string('customer_tel')->unique()->nullable();
            $table->string('customer_address')->nullable();
            $table->string('communes')->nullable();
            $table->string('districts')->nullable();
            $table->string('provinces')->nullable();
            $table->string('villages')->nullable();
            $table->string('commune_id')->nullable();
            $table->string('district_id')->nullable();
            $table->string('province_id')->nullable();
            $table->string('village_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('image')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
            $table->foreign("created_by")->references("id")->on("users")->onDelete("cascade");
        });

        Customers::insert([
            'customer_name' => 'Unknown',
            'customer_email' => null,
            'customer_tel' => null,
            'customer_address' => null,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
