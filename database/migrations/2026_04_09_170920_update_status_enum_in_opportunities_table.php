<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('opportunities', function (Blueprint $table) {
        // إذا كنت تستخدم MySQL، يفضل استخدام التعديل المباشر
        DB::statement("ALTER TABLE opportunities MODIFY COLUMN status ENUM('open', 'closed', 'completed', 'hidden') DEFAULT 'open'");
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            //
        });
    }
};