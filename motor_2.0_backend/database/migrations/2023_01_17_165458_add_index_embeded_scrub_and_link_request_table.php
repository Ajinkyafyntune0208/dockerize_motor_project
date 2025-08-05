<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexEmbededScrubAndLinkRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('embedded_scrub_data', function (Blueprint $table) {
            if (Schema::hasColumn('embedded_scrub_data', 'attempts') && Schema::hasColumn('embedded_scrub_data', 'is_processed')) {
                $table->index(['attempts', 'is_processed']);
            }
        });

        Schema::table('embedded_link_request_data', function (Blueprint $table) {
            if (Schema::hasColumn('embedded_link_request_data', 'attempts') && Schema::hasColumn('embedded_link_request_data', 'is_processed')) {
                $table->index(['attempts', 'is_processed']);
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
        //
    }
}
