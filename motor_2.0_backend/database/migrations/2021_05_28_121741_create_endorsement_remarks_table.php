<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEndorsementRemarksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('endorsement_remarks', function (Blueprint $table) {
            $table->integer('endorsement_remark_id', true);
            $table->integer('endorsement_id')->nullable();
            $table->integer('endorsement_staus_id')->nullable();
            $table->integer('additional_value')->nullable();
            $table->string('remarks', 256)->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->integer('created_by')->nullable();
            $table->dateTime('remark_created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('endorsement_remarks');
    }
}
