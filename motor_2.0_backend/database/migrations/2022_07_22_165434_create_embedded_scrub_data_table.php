<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmbeddedScrubDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('embedded_scrub_data'))
        {
            Schema::create('embedded_scrub_data', function (Blueprint $table) {
                $table->id();
                $table->string('rc_number')->nullable();
                $table->longText('request')->nullable();
                $table->longText('response')->nullable();
                $table->integer('attempts')->default(0);
                $table->integer('is_processed')->default(0);
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('embedded_scrub_data'))
        {
            Schema::dropIfExists('embedded_scrub_data');
        }
    }
}
