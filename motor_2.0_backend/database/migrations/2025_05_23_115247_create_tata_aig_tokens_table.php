<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataAigTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tata_aig_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->index();
            $table->string('client_secret')->index();
            $table->text('token');
            $table->dateTime('expired_at')->index();
            $table->timestamps();

            $table->index(['client_id', 'client_secret', 'expired_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tata_aig_tokens');
    }
}
