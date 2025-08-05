<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmbeddedScrubPdfDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('embedded_scrub_pdf_data'))
        {
            Schema::create('embedded_scrub_pdf_data', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_id')->nullable();
                $table->longText('pdf_data')->nullable();
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
        if (Schema::hasTable('embedded_scrub_pdf_data'))
        {
            Schema::dropIfExists('embedded_scrub_pdf_data');
        }
    }
}
