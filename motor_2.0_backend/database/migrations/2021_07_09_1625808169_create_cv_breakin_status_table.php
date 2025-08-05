<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvBreakinStatusTable extends Migration
{
    public function up()
    {
        Schema::create('cv_breakin_status', function (Blueprint $table) {

		$table->id('cv_breakin_id');
		$table->string('ic_id')->nullable();
		$table->string('user_proposal_id')->nullable();
		$table->string('breakin_number')->nullable();
		$table->string('breakin_status')->nullable();
		$table->string('breakin_status_final')->nullable();
		$table->string('payment_url')->nullable();
		$table->string('breakin_check_url')->nullable();
		$table->text('breakin_response')->nullable();
		$table->date('payment_end_date')->nullable();
		$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cv_breakin_status');
    }
}
