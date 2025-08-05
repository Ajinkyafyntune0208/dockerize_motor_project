<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEndorsementTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('endorsement_type', function (Blueprint $table) {
            $table->integer('endorsement_type_id', true);
            $table->string('apiname')->nullable();
            $table->string('table_name')->nullable();
            $table->string('correct_val_idname')->nullable();
            $table->string('endorsement_type_name', 50)->nullable();
            $table->string('endorsement_type_code', 500)->nullable();
            $table->integer('endorsement_category')->nullable();
            $table->integer('endo_doccheck')->nullable()->default(1)->comment('1 = yes , 0 = no');
            $table->integer('endo_feildtype')->nullable()->default(1)->comment('1 = textbox, 2 = dropdown');
            $table->string('endorsement_document_name')->nullable();
            $table->integer('display_order')->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->string('is_financial')->default('Y');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('endorsement_type');
    }
}
