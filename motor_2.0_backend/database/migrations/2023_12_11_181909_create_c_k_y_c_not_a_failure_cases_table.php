<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCKYCNotAFailureCasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!(Schema::hasTable('ckyc_not_a_failure_cases'))) {
            Schema::create('ckyc_not_a_failure_cases', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('message');
                $table->integer('active');
                $table->timestamps();
            });
        }
        Artisan::call('db:seed --class=CKYCNotAFailureCasesSeeder');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ckyc_not_a_failure_cases');
    }
}
