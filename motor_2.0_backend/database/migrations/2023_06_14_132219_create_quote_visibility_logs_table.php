<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuoteVisibilityLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quote_visibility_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('quote_webservice_id')->index();
            $table->integer('enquiry_id');
            $table->string('product')->nullable();
            $table->string('method_name')->nullable()->index();
            $table->integer('master_policy_id')->nullable();
            $table->string('company', 50)->nullable()->index();
            $table->string('section', 20)->nullable()->index();
            $table->integer('response_time')->default(0)->nullable();
            $table->string('status', 20)->nullable()->index();
            $table->text('message')->nullable();
            $table->string('transaction_type', 20)->nullable()->index();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quote_visibility_logs');
    }
}
