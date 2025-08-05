<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVahanSerialNumberCountToProposalExtraFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            $table->integer('vahan_serial_number_count')->nullable()->after('upload_secondary_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            //
        });
    }
}
