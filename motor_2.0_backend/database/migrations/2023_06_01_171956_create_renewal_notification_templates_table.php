<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalNotificationTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_notification_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 100)->nullable();
            $table->string('days', 5)->nullable();
            $table->text('template')->nullable();
            $table->set('variables_in_template', ['proposer_name', 'proposer_mobile', 'proposer_emailid', 'vehicle_registration_number', 'premium_amount', 'policy_start_date', 'policy_end_date'])->nullable();
            $table->string('footer', 200)->nullable();
            $table->enum('media_type', ['TEXT', 'IMAGE', 'VIDEO'])->default('TEXT');
            $table->string('media_path', 255)->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('renewal_notification_templates');
    }
}
