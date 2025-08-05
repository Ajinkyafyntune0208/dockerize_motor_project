<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadPageOtpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_page_otps', function (Blueprint $table) {
            $table->id();
            $table->string("mobile_number", 50);
            $table->string("email");
            $table->string("otp", 50);
            $table->tinyInteger("is_expired")->default(0);
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
        Schema::dropIfExists('lead_page_otps');
    }
}
