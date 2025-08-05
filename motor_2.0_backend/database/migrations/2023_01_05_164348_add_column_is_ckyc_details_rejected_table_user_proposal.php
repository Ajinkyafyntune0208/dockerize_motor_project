<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsCkycDetailsRejectedTableUserProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if ( ! Schema::hasColumn('user_proposal', 'is_ckyc_details_rejected')) {
                $table->enum('is_ckyc_details_rejected', ['Y', 'N'])->default('N')->after('is_ckyc_verified');
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
        //
    }
}
