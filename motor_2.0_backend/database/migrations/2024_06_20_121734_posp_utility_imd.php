<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PospUtilityImd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('posp_utility_imd'))
        {
            Schema::create('posp_utility_imd', function (Blueprint $table) {
                $table->increments('imd_id'); //Primary Key
                $table->string('imd_code', 100)->collation('utf8mb4_unicode_ci');
                $table->json('imd_fields_data')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->integer('updated_by')->nullable();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
                $table->enum('created_source', ['DASHBOARD', 'MOTOR'])->nullable(false);
                $table->enum('updated_source', ['DASHBOARD', 'MOTOR'])->nullable()->default(null);
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
        Schema::dropIfExists('posp_utility_imd');
    }
}
