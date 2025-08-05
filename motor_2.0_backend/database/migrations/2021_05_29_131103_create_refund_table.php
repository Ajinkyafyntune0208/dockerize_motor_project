<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('policy_number', 50)->default('');
            $table->integer('user_id')->nullable();
            $table->string('remarks', 100)->nullable();
            $table->date('date_of_refund')->nullable();
            $table->integer('total_part_cost')->nullable();
            $table->integer('total_labour_cost')->nullable();
            $table->integer('refund_amount')->nullable();
            $table->string('document_type', 100);
            $table->string('docs', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('refund');
    }
}
