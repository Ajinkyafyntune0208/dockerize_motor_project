<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotificationTypeToRenewalNotificationTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('renewal_notification_templates', function (Blueprint $table) {
            $table->string('notification_type')->nullable();
            $table->string('method')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('renewal_notification_templates', function (Blueprint $table) {
            $table->dropColumn('notification_type');
            $table->dropColumn('method');
        });
    }
}
