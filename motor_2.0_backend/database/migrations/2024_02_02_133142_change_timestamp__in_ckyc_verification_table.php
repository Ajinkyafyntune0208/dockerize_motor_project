<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTimestampInCkycVerificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    
        Schema::table('ckyc_verification_types', function (Blueprint $table) {
            $table->dateTime('created_at')->default('CURRENT_TIMESTAMP')->change();
            $table->dateTime('updated_at')->default('CURRENT_TIMESTAMP')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ckyc_verification_types', function (Blueprint $table) {
            //
        });
    }
}
