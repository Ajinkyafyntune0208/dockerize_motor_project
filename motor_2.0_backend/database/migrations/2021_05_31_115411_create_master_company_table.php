<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCompanyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_company', function (Blueprint $table) {
            $table->increments('company_id');
            $table->string('company_name')->default('');
            $table->string('company_shortname', 50)->default('');
            $table->string('company_alias', 50)->nullable();
            $table->string('branch', 6000)->nullable();
            $table->string('manager_name', 500)->nullable();
            $table->integer('email')->nullable();
            $table->integer('contact_no')->nullable();
            $table->string('url')->nullable()->default('');
            $table->text('spoc')->nullable();
            $table->string('logo')->nullable()->default('');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('motor_column')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_company');
    }
}
