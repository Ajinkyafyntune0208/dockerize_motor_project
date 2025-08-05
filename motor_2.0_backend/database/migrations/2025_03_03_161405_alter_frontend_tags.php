<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterFrontendTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            if ( Schema::hasColumn('proposal_extra_fields', 'frontend_tags')) {
                $table->dropColumn('frontend_tags'); // Drop only if exists
            }
        });

        Schema::table('selected_addons', function (Blueprint $table) {
            if ( ! Schema::hasColumn('selected_addons', 'frontend_tags')) {
                $table->longText('frontend_tags')->nullable()->after('compulsory_personal_accident');
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
        // Schema::table('selected_addons', function (Blueprint $table) {
        //     //
        // });
    }
}
