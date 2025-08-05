<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusColumnInIcVersionConfigurators extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
            if (!Schema::hasColumn('ic_version_configurators', 'status')) {
                Schema::table('ic_version_configurators', function (Blueprint $table) {
                    $table->enum('status', ['Active', 'InActive'])
                    ->after('segment_id')
                    ->nullable();
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
