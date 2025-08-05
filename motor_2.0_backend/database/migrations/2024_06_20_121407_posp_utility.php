<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PospUtility extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('posp_utility')) 
        {
            Schema::create('posp_utility', function (Blueprint $table) {
                $table->increments('utility_id'); // UNSIGNED INTEGER AUTO_INCREMENT PRIMARY KEY
                $table->unsignedInteger('seller_user_id'); // UNSIGNED INTEGER
                $table->enum('seller_type', ['POSP', 'MISP', 'EMPLOYEE', 'PARTNER', 'B2C']); // ENUM
                $table->timestamp('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posp_utility');
    }
}
