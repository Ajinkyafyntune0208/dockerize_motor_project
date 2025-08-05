<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterDealerMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_dealer_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_product_journey_id')->unsigned()->nullable();            
            $table->string('dealercode')->nullable();
            $table->string('dealermastercode')->nullable();
            $table->string('dealermastername')->nullable();
            $table->string('dealermasterlocation')->nullable();
            $table->string('dealermastercategory')->nullable();
            $table->string('dealermasterzone')->nullable();
            $table->string('dealermasterregion')->nullable();
            $table->string('dealermasterstate')->nullable();
            $table->string('dealermasterspocname')->nullable();
            $table->string('dealermasterspocemail')->nullable();
            $table->string('dealermasterrmname')->nullable();
            $table->string('dealermasteremailid')->nullable();
            $table->string('dealermasterbranchname')->nullable();
            $table->string('dealermasterheadname')->nullable();
            $table->string('dealermasterbranchheadid')->nullable();
            $table->string('dealermasterbranchheademail')->nullable();
            $table->string('dealermasteremployeename')->nullable();
            $table->string('dealermasterzoneheadid')->nullable();
            $table->string('dealermasterzoneheademail')->nullable();
            $table->integer('oem_id')->nullable();
            $table->integer('suboem_id')->nullable();
            $table->string('dealername')->nullable();
            $table->string('dealerlocation')->nullable();
            $table->timestamps();
            $table->index('user_product_journey_id');
            $table->index('dealercode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_dealer_mapping');
    }
}
