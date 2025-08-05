<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOneTableInProposalExtraFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('proposal_extra_fields')) {
            if (!Schema::hasColumn('proposal_extra_fields', 'reference_code')) {
                Schema::table('proposal_extra_fields', function (Blueprint $table) {
                    $table->string('reference_code')->nullable()->after('original_agent_details');
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
        Schema::dropIfExists('one_table_in_proposal_extra_fields');
    }
}
