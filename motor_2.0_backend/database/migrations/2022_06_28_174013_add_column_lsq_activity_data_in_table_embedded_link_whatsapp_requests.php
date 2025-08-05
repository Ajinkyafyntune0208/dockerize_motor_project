<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnLsqActivityDataInTableEmbeddedLinkWhatsappRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('embedded_link_whatsapp_requests', function (Blueprint $table) {
            if ( ! Schema::hasColumn('embedded_link_whatsapp_requests', 'lsq_activity_data'))
            {
                $table->longText('lsq_activity_data')->nullable();
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
        Schema::table('embedded_link_whatsapp_requests', function (Blueprint $table) {
            if (Schema::hasColumn('embedded_link_whatsapp_requests', 'lsq_activity_data'))
            {
                $table->dropColumn('lsq_activity_data');
            }
        });
    }
}
