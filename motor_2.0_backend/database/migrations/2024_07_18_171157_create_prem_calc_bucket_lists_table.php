<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcBucketListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_bucket_lists', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('prem_calc_bucket_id');
            $table->bigInteger('label_id');
            $table->enum('type', ['MANDATORY', 'OPTIONAL', 'EXCLUDED']);
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            
            $table->index('deleted_at');
            $table->index('prem_calc_bucket_id');
            $table->index('label_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prem_calc_bucket_lists');
    }
}
