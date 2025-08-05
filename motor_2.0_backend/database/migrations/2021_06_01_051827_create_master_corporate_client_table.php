<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCorporateClientTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_corporate_client', function (Blueprint $table) {
            $table->integer('corp_client_id', true);
            $table->string('corp_client_name')->default('');
            $table->string('corp_client_shortname', 50)->default('');
            $table->string('url')->nullable()->default('');
            $table->text('spoc')->nullable();
            $table->string('logo')->nullable()->default('');
            $table->string('status')->default('Active');
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_corporate_client');
    }
}
