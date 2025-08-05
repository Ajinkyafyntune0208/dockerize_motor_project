<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColmnCkycUploadDocument extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    { Schema::table('ckyc_upload_documents', function (Blueprint $table) {
        if ( ! Schema::hasColumn('ckyc_upload_documents', 'cky_doc_data')) {
            $table->json('cky_doc_data')->nullable()->after('doc_name');
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
        Schema::table('ckyc_upload_documents', function (Blueprint $table) {
        if (Schema::hasColumn('ckyc_upload_documents', 'cky_doc_data')) {
            $table->dropColumn('cky_doc_data');
        }
    });
    }
}
