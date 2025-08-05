<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaFinancierBranchMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('united_india_financier_branch_masters', function (Blueprint $table) {
            $table->bigInteger('financier_code')->nullable();
            $table->bigInteger('financier_branch_code')->nullable();
            $table->text('branch_name')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->bigInteger('micr_code')->nullable();
            $table->text('branch_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_financier_branch_masters');
    }
}
