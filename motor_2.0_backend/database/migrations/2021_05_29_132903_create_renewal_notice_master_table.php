<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRenewalNoticeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renewal_notice_master', function (Blueprint $table) {
            $table->integer('renewal_notice_id', true);
            $table->string('insuured_name', 50);
            $table->string('rto', 50);
            $table->string('corp_address', 150);
            $table->string('contact_no', 50);
            $table->string('email', 50);
            $table->string('registration_no', 50);
            $table->string('policy_no', 50);
            $table->date('policy_start_date')->nullable();
            $table->date('policy_end_date')->nullable();
            $table->date('policy_issued_date')->nullable();
            $table->string('make', 50);
            $table->string('model_varient', 50);
            $table->string('engine_no', 50);
            $table->string('chassis_no', 50);
            $table->integer('mfg_year');
            $table->integer('seats');
            $table->string('body_type', 50);
            $table->string('cc', 50);
            $table->string('total_idv', 50);
            $table->dateTime('created_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('mail_send_status')->default('False');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('renewal_notice_master');
    }
}
