<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHdfcErgoTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hdfc_ergo_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 100)->index();
            $table->text('token');
            $table->dateTime('expired_at')->index();
            $table->timestamps();

            $table->index(['product_code', 'expired_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hdfc_ergo_tokens');
    }
}
