<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Updatemagmaposreqresponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('magma_pos_req_response')) {
            if (Schema::hasColumn('magma_pos_req_response', 'headers') && Schema::hasColumn('magma_pos_req_response', 'message') && Schema::hasColumn('magma_pos_req_response', 'status')) {
                Schema::table('magma_pos_req_response', function (Blueprint $table) {
                    $table->longText('headers')->nullable()->change();
                    $table->longText('message')->nullable()->change();
                    $table->longText('status')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('magma_pos_req_response')) {
            if (Schema::hasColumn('magma_pos_req_response', 'headers') && Schema::hasColumn('magma_pos_req_response', 'message') && Schema::hasColumn('magma_pos_req_response', 'status')) {
                Schema::table('magma_pos_req_response', function (Blueprint $table) {
                    $table->string('headers')->nullable()->change();
                    $table->string('message')->nullable()->change();
                    $table->string('status')->nullable()->change();
                });
            }
        }
    }
}
