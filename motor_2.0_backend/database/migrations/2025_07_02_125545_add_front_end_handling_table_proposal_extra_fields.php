<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrontEndHandlingTableProposalExtraFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            if (Schema::hasTable('proposal_extra_fields') && !Schema::hasColumn('proposal_extra_fields', 'frontend_handling')) {
                $table->json('frontend_handling')->nullable()->after('vahan_serial_number_count');
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
    }
}
