<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserCreationRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('user_creation_request_response')) {
            Schema::create('user_creation_request_response', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('mobile_no')->nullable()->index();
                $table->text('request')->nullable();
                $table->text('response')->nullable();
                $table->text('url')->nullable();
                $table->timestamps();
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
        //
    }
}
