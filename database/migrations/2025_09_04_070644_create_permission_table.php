<?php

use App\Models\Permission;
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
        Schema::create('permission', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('menu_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('menu_id')->references('menu_id')->on('menus')->onDelete('cascade');
            $table->primary(['user_id', 'menu_id']);
            $table->timestamps();
        });

        Permission::insert([
            ['user_id' => 1, 'menu_id' => 1],
            ['user_id' => 1, 'menu_id' => 2],
            ['user_id' => 1, 'menu_id' => 3],
            ['user_id' => 1, 'menu_id' => 4],
            ['user_id' => 1, 'menu_id' => 5],
            // ['user_id' => 1, 'menu_id' => 6],
            // ['user_id' => 1, 'menu_id' => 7],
            ['user_id' => 1, 'menu_id' => 8],
            ['user_id' => 1, 'menu_id' => 9],
            ['user_id' => 1, 'menu_id' => 10],
            // ['user_id' => 1, 'menu_id' => 11],
            ['user_id' => 1, 'menu_id' => 12],
            ['user_id' => 1, 'menu_id' => 13],
            ['user_id' => 1, 'menu_id' => 14],
            ['user_id' => 1, 'menu_id' => 15],
            ['user_id' => 1, 'menu_id' => 16],
            ['user_id' => 1, 'menu_id' => 17],
            ['user_id' => 1, 'menu_id' => 18],
            ['user_id' => 1, 'menu_id' => 19],
            ['user_id' => 1, 'menu_id' => 20],
            ['user_id' => 1, 'menu_id' => 21],
            ['user_id' => 1, 'menu_id' => 22],
            ['user_id' => 1, 'menu_id' => 23],
            ['user_id' => 1, 'menu_id' => 24],
            ['user_id' => 1, 'menu_id' => 25],
            ['user_id' => 1, 'menu_id' => 26],
            ['user_id' => 1, 'menu_id' => 27],
            ['user_id' => 1, 'menu_id' => 28],
            ['user_id' => 1, 'menu_id' => 29],
            ['user_id' => 1, 'menu_id' => 30],
            ['user_id' => 1, 'menu_id' => 31],
            ['user_id' => 1, 'menu_id' => 32],
            ['user_id' => 1, 'menu_id' => 33],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission');
    }
};
