<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreviousPolicyTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('previous_policy_type', function (Blueprint $table) {
            $table->integer('previous_policy_type_id', true);
            $table->string('previous_policy_type_name', 200);
            $table->string('previous_policy_type_code', 100)->nullable();
            $table->dateTime('created_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('previous_policy_type');
    }
}
