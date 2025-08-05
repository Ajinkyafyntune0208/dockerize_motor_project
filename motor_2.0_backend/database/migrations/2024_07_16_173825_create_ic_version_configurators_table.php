<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcVersionConfiguratorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_version_configurators', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('ic_id')->nullable()->index();
            $table->string('ic_alias', 50)->index();
            $table->integer('version')->default(0)->index();
            $table->enum('kit_type', ['json', 'xml'])->default('json')->index();
            $table->unsignedBigInteger('segment_id')->nullable()->index();
            $table->string('description', 250)->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_version_configurators');
    }
}
