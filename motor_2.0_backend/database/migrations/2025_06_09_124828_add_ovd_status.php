<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOvdStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposer_ckyc_details', function (Blueprint $table) {
            if (!Schema::hasColumn('proposer_ckyc_details', 'ovd_status')) {
                $table->enum('ovd_status', ['Y', 'N'])->default('N')->after('is_document_upload');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('proposer_ckyc_details', function (Blueprint $table) {
        //     //
        // });
    }
}