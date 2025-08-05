<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcLabelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_labels', function (Blueprint $table) {
            $table->id();
            $table->string('label_name', 250)->nullable();
            $table->string('label_key', 250)->nullable();
            $table->enum('label_group', [
                'Own Damage', 'Liability', 'CPA', 'Addons', 'Accessories', 'Additional Covers', 'IMT', 'Discounts', 'Deductibles', 'Others'
            ])->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by');

            $table->index('label_group');
            $table->index('deleted_at');
            $table->index('label_name');
            $table->index('label_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prem_calc_labels');
    }
}
