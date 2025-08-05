<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddZoneColumnInOrientalRtoMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('oriental_rto_master'))
        {
            if (!Schema::hasColumn('oriental_rto_master', 'rto_zone'))
            {
                Schema::table('oriental_rto_master', function (Blueprint $table) {
                    $table->string('rto_zone', 10)->nullable();
                });
            }
            Artisan::call('db:seed --class=OrientalRtoMasterSeeder');
        }
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('oriental_rto_master', function (Blueprint $table) {
            //
        });
    }
}
