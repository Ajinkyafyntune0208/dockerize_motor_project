<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCkycRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ckyc_request_responses', function (Blueprint $table) {
            $table->id();
            $table->string('user_product_journey_id')->nullable();
            $table->string('user_proposal_id')->nullable();
            $table->string('ic_id')->nullable();
            $table->string('kyc_search_data')->nullable();
            $table->string('ic_kyc_no')->nullable();
            $table->longText('kyc_response')->nullable();
            $table->enum('kyc_status',['1','0'])->default('0');
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ckyc_request_responses');
    }
}
