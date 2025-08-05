<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAdminUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update admin@fyntune.com with motor@fyntune.com
        DB::table( 'user' )
        ->where( 'email', 'admin@fyntune.com' )
        ->update(
            [ 
                'email' => 'motor@fyntune.com'
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
