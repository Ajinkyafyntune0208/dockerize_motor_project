<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCorpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_corp', function (Blueprint $table) {
            $table->integer('corp_id', true);
            $table->string('corp_name');
            $table->string('corp_shortname', 50);
            $table->string('corp_address', 50);
            $table->string('corp_email', 50);
            $table->string('corp_contact', 50);
            $table->string('url')->nullable();
            $table->text('spoc')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('Active');
            $table->string('created_by', 50);
            $table->dateTime('created_date');
            $table->string('updated_by', 50)->nullable();
            $table->dateTime('updated_date')->nullable()->useCurrent();
            $table->string('gst_number', 15)->nullable();
            $table->string('pan_number', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_corp');
    }
}
