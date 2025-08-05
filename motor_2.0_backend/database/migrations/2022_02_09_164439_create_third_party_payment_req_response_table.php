<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThirdPartyPaymentReqResponseTable extends Migration
{
    public function up()
    {
        Schema::create('third_party_payment_req_response', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_id')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('third_party_payment_req_response');
    }
}
