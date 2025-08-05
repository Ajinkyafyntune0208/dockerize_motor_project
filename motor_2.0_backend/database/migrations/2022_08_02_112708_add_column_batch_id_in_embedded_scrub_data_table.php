<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBatchIdInEmbeddedScrubDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('embedded_scrub_data', function (Blueprint $table) {
            if ( ! Schema::hasColumn('embedded_scrub_data', 'batch_id'))
            {
                $table->string('batch_id')->nullable()->after('rc_number');
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
        Schema::table('embedded_scrub_data', function (Blueprint $table) {
            if (Schema::hasColumn('embedded_scrub_data', 'batch_id'))
            {
                $table->dropColumn('batch_id');
            }
        });
    }
}
