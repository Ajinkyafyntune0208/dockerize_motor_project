<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnMetaDataInProposerCkycDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposer_ckyc_details', function (Blueprint $table) {
            if ( ! Schema::hasColumn('proposer_ckyc_details', 'meta_data')) {
                $table->json('meta_data')->nullable()->after('is_document_upload');
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
            if (Schema::hasColumn('proposer_ckyc_details', 'meta_data')) {
                $table->dropColumn('meta_data');
            }
        });
    }
}
