<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCkycMetaDataInUserProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_proposal', function (Blueprint $table) {
            if ( ! Schema::hasColumn('user_proposal', 'ckyc_meta_data')) {
                $table->json('ckyc_meta_data')->nullable()->after('ckyc_number');
            }

            if ( ! Schema::hasColumn('user_proposal', 'is_ckyc_verified')) {
                $table->enum('is_ckyc_verified', ['Y', 'N'])->default('N');
            }

            if ( ! Schema::hasColumn('user_proposal', 'ckyc_type')) {
                $table->enum('ckyc_type', ['ckyc_number', 'pan_card', 'aadhar_card'])->default('pan_card');
            }

            if ( ! Schema::hasColumn('user_proposal', 'ckyc_type_value')) {
                $table->text('ckyc_type_value')->nullable();
            }

            if ( ! Schema::hasColumn('user_proposal', 'ckyc_reference_id')) {
                $table->text('ckyc_reference_id')->nullable()->after('ckyc_number');
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
        Schema::table('user_proposal', function (Blueprint $table) {
            if (Schema::hasColumn('user_proposal', 'ckyc_meta_data')) {
                $table->dropColumn('ckyc_meta_data');
            }

            if (Schema::hasColumn('user_proposal', 'is_ckyc_verified')) {
                $table->dropColumn('is_ckyc_verified');
            }

            if (Schema::hasColumn('user_proposal', 'ckyc_type')) {
                $table->dropColumn('ckyc_type');
            }

            if (Schema::hasColumn('user_proposal', 'ckyc_type_value')) {
                $table->dropColumn('ckyc_type_value');
            }

            if (Schema::hasColumn('user_proposal', 'ckyc_reference_id')) {
                $table->dropColumn('ckyc_reference_id');
            }
        });
    }
}
