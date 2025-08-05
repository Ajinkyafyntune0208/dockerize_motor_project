<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrontendTextTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            if ( ! Schema::hasColumn('proposal_extra_fields', 'frontend_tags')) {
                $table->json('frontend_tags')->nullable()->after('vahan_serial_number_count');
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
        Schema::table('proposal_extra_fields', function (Blueprint $table) {
            if ( ! Schema::hasColumn('proposal_extra_fields', 'frontend_tags')) {
                $table->dropColumn('frontend_tags');
            }
        });
    }
}
