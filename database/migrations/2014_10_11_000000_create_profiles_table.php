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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string("profile_name");
            $table->string("telephone");
            $table->date("start_date");
            $table->integer("term");
            $table->date("end_date");
            $table->integer("created_by");
            $table->boolean("is_deleted")->default(0);
            $table->string("image")->nullable();
            $table->timestamps();
        });

        \App\Models\Profile::create([
            'profile_name' => 'admin',
            'telephone' => '0979772133',
            'start_date' => '2025-07-21',
            'term' => 12,
            'end_date' => '2026-07-21',
            'created_by' => 0,
            'image' => null, // path or filename
        ]);
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
