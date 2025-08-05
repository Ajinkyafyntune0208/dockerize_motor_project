<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDefaultApplicableCoversTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('default_applicable_covers', function (Blueprint $table) {
            $table->id();
            $table->enum('section',['CAR','BIKE','PCV','GCV'])->nullable();
            $table->enum('cover_type',['additional_covers','discounts','accessories','addons','compulsory_personal_accident','voluntary_insurer_discounts'])->nullable();
            $table->string('cover_name')->nullable();
            $table->integer('sum_insured')->nullable();
            $table->enum('status',['Y','N'])->nullable()->default('N');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('default_applicable_covers');
    }
}
