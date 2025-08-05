<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropStatusColumnFromIcVersionConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ic_version_configurators', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('updated_by');
            $table->dropColumn('created_by');
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
            $table->dropColumn('deleted_at');

            $table->string('slug', 350)->after('business_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ic_version_configurators', function (Blueprint $table) {
            //
        });
    }
}
