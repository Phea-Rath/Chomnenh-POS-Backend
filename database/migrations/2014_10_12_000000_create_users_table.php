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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string("username");
            $table->unsignedBigInteger("profile_id");
            $table->string("phone_number");
            $table->string("password");
            $table->unsignedInteger("role_id");
            $table->string("role");
            $table->integer("status");
            $table->integer("created_by");
            $table->string("image")->nullable();
            $table->boolean("is_deleted")->default(0);
            $table->rememberToken();
            $table->date("login_at")->nullable();
            $table->timestamps();
            $table->foreign("profile_id")->references('id')->on("profiles")->onDelete('cascade');
            $table->foreign("role_id")->references('role_id')->on("roles");
        });

        \App\Models\Users::create([
            'username' => 'superadmin',
            'profile_id' => 1,
            'phone_number' => '0979772133',
            'password' => bcrypt('12345678'), // hashed password
            'role_id' => 1,
            'role' => 'superadmin',
            'status' => 1,
            'created_by' => 0,
            'image' => null, // path or filename
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
