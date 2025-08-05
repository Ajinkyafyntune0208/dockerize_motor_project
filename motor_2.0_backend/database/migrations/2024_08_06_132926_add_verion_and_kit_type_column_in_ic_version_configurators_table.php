<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVerionAndKitTypeColumnInIcVersionConfiguratorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ic_version_configurators', function (Blueprint $table) {
            //
            if (!Schema::hasColumn('ic_version_configurators', 'version')) {
                Schema::table('ic_version_configurators', function (Blueprint $table) {
                    $table->string('version' , 50)
                    ->after('integration_type');
                });
            }

            if (!Schema::hasColumn('ic_version_configurators', 'kit_type')) {
                Schema::table('ic_version_configurators', function (Blueprint $table) {
                    $table->string('kit_type' , 50)
                    ->after('version');
                });
            }
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
