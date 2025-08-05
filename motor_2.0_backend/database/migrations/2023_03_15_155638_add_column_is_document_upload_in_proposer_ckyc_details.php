<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsDocumentUploadInProposerCkycDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposer_ckyc_details', function (Blueprint $table) {
            if ( ! Schema::hasColumn('proposer_ckyc_details', 'is_document_upload')) {
                $table->enum('is_document_upload', ['Y', 'N'])->default('N')->after('organization_type');
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
            if (Schema::hasColumn('proposer_ckyc_details', 'is_document_upload')) {
                $table->dropColumn('is_document_upload');
            }
        });
    }
}
