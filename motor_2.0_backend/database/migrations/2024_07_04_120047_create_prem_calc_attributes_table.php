<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('ic_alias', 150);
            $table->string('integration_type', 50)->default('');
            $table->string('segment', 50);
            $table->string('business_type', 100);
            $table->string('attribute_name', 100)->default('');
            $table->text('attribute_trail')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by');

            $table->index('ic_alias');
            $table->index('integration_type');
            $table->index('segment');
            $table->index('business_type');
            $table->index('attribute_name');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prem_calc_attributes');
    }
}
