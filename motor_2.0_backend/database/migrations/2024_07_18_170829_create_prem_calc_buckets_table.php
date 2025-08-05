<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcBucketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_buckets', function (Blueprint $table) {
            $table->id();
            $table->string('bucket_name', 100);
            $table->double('discount');
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

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
        Schema::dropIfExists('prem_calc_buckets');
    }
}
