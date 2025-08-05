<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropColumnFromIcVersionConfigurators extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ic_version_configurators', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->dropColumn('ic_id');
            $table->dropColumn('version');
            $table->dropColumn('kit_type');
            $table->dropColumn('segment_id');
            $table->string('segment', 100)->after('ic_alias');
            $table->bigInteger('created_by')->nullable()->change();
            $table->bigInteger('updated_by')->nullable()->change();
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
