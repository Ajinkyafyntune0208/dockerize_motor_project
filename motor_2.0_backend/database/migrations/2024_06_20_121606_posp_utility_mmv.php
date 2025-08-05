<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PospUtilityMmv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('posp_utility_mmv')) 
        {
            Schema::create('posp_utility_mmv', function (Blueprint $table) {
                $table->increments('utility_mmv_id');
                $table->unsignedInteger('utility_id');
                $table->integer('segment_id');
                $table->string('ic_integration_type');
                $table->json('matrix')->nullable(false);
                $table->timestamps();
                $table->integer('created_by')->nullable(false);
                $table->integer('updated_by')->nullable()->default(null);
                $table->enum('created_source', ['DASHBOARD', 'MOTOR'])->nullable(false);
                $table->enum('updated_source', ['DASHBOARD', 'MOTOR'])->nullable()->default(null);
                $table->softDeletes();

                $table->foreign('utility_id')->references('utility_id')->on('posp_utility')->onDelete('cascade');
                $table->foreign('segment_id')
                  ->references('product_sub_type_id')
                  ->on('master_product_sub_type')
                  ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posp_utility_mmv');
    }
}
