<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPermanantAddressInProposerCkycDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposer_ckyc_details', function (Blueprint $table) {
            if ( ! Schema::hasColumn('proposer_ckyc_details', 'permanent_address')) {
                $table->text('permanent_address')->nullable()->after('is_document_upload');
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
        Schema::table('proposer_ckyc_details', function (Blueprint $table) {
            if (Schema::hasColumn('proposer_ckyc_details', 'permanent_address')) {
                $table->dropColumn('permanent_address');
            }
        });
    }
}
