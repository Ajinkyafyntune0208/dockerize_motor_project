<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInProposalExtraFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {


        if (Schema::hasTable('proposal_extra_fields')) {
            if (!Schema::hasColumn('proposal_extra_fields', 'original_agent_details')) {
                Schema::table('proposal_extra_fields', function (Blueprint $table) {
                    $table->json('original_agent_details')->nullable()->after('cis_url');
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
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            //
        });
    }
}