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

        $permissions = [];

        for ($i = 1; $i <= 31; $i++) {

            $permissions[] = [
                'user_id' => 1,
                'menu_id' => $i,
            ];
        }

        Permission::insert($permissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission');
    }
};
