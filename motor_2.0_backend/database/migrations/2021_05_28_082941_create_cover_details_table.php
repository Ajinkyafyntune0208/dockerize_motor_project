<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoverDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cover_details', function (Blueprint $table) {
            $table->integer('cover_details_id', true);
            $table->integer('policy_id')->index('fk_policy_cert_id');
            $table->integer('cover_master_id')->index('fk_cover_master_id');
            $table->integer('policy_cert_id')->index('policy_cert_id');
            $table->string('cover_name');
            $table->string('cover_description');
            $table->string('section_name');
            $table->integer('cover_sum_insured');
            $table->integer('cover_rate');
            $table->integer('cover_premium');
            $table->integer('cover_discount_rate');
            $table->integer('cover_discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cover_details');
    }
}
