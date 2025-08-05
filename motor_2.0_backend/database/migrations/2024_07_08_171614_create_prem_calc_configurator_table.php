<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcConfiguratorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_configurator', function (Blueprint $table) {
            $table->id();
            $table->string('ic_alias', 150)->charset('utf8mb4')->collation('utf8mb4_general_ci');
            $table->string('integration_type', 50)->charset('utf8mb4')->collation('utf8mb4_general_ci')->default('');
            $table->string('segment', 50)->charset('utf8mb4')->collation('utf8mb4_general_ci');
            $table->string('business_type', 100)->charset('utf8mb4')->collation('utf8mb4_general_ci');
            $table->integer('label_id');
            $table->enum('calculation_type', ['attribute', 'formula', 'custom_val', 'na'])->charset('utf8mb4')->collation('utf8mb4_general_ci')->default('na');
            $table->integer('attribute_id')->nullable();
            $table->integer('formula_id')->nullable();
            $table->string('custom_val', 250)->charset('utf8mb4')->collation('utf8mb4_general_ci')->nullable();
            $table->timestamps();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->timestamp('deleted_at')->nullable();

            $table->index('ic_alias');
            $table->index('integration_type');
            $table->index('segment');
            $table->index('business_type');
            $table->index('calculation_type');
            $table->index('attribute_id');
            $table->index('formula_id');
            $table->index('deleted_at');
            $table->index('label_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prem_calc_configurator');
    }
}
