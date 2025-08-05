<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameIdColumnInFutureGeneraliPrevInsurerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('future_generali_prev_insurer') && !Schema::hasColumn('future_generali_prev_insurer', 'insurer_id')) {
            Schema::table('future_generali_prev_insurer', function (Blueprint $table) {
                $table->renameColumn('id', 'insurer_id');
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
        if (Schema::hasTable('future_generali_prev_insurer')) {
            Schema::table('future_generali_prev_insurer', function (Blueprint $table) {
                $table->renameColumn('insurer_id', 'id');
            });
        }
    }
}
