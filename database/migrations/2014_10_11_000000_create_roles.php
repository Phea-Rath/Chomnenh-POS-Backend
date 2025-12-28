<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Roles;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('role_id');
            $table->string('role_name');
            $table->string('role_description')->nullable();
            $table->string('created_by')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
        });

        Roles::insert([
            ['role_name' => 'superadmin', 'role_description' => 'Super Administrator', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'admin', 'role_description' => 'Administrator', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'user', 'role_description' => 'Regular User', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
