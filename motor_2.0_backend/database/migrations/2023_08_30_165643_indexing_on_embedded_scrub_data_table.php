<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndexingOnEmbeddedScrubDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('embedded_scrub_data', function (Blueprint $table) {
            $table->index('rc_number');
            $table->index('batch_id');
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
            $table->dropIndex('embedded_scrub_data_rc_number_index');
            $table->dropIndex('embedded_scrub_data_batch_id_index');
        });
    }
}
