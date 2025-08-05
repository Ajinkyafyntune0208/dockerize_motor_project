<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInIcVersionConfiguratorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('ic_version_configurators', 'integration_type')) {
            Schema::table('ic_version_configurators', function (Blueprint $table) {
                $table->string('integration_type', 50)->after('ic_alias')->nullable();
            });
        }
        if (!Schema::hasColumn('ic_version_configurators', 'business_type')) {
            Schema::table('ic_version_configurators', function (Blueprint $table) {
                $table->string('business_type', 50)->after('integration_type')->nullable();
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
        Schema::table('ic_version_configurators', function (Blueprint $table) {
            //
        });
    }
}
