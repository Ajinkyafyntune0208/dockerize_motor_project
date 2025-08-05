<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\AddDataInPassordPolicyTable;

class CreatePasswordPoliciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('password_policy', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('key');
            $table->unique('key');
            $table->string('value');
            $table->timestamps();
        });

        $policy_seed = new AddDataInPassordPolicyTable();
        $policy_seed->run();
    }   

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('password_policies');
    }
}
