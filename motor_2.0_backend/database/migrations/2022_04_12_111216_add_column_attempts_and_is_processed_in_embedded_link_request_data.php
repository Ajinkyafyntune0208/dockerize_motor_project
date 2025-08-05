<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAttemptsAndIsProcessedInEmbeddedLinkRequestData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('embedded_link_request_data', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->after('response')->nullable();
            $table->boolean('is_processed')->after('attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('embedded_link_request_data', function (Blueprint $table) {
            $table->dropIfExists('attempts');
            $table->dropIfExists('is_processed');
        });
    }
}
