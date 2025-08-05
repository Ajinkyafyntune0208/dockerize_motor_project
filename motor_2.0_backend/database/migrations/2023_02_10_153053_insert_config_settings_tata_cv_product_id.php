<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertConfigSettingsTataCvProductId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $product_id_data = \App\Models\ConfigSettings::where('key', 'constants.IcConstants.tata_aig.cv.PRODUCT_ID')
            ->first();
        if($product_id_data === null)
        {
            DB::table('config_settings')->insert([
                [
                    'label' => 'PRODUCT_ID',
                    'key' => 'constants.IcConstants.tata_aig.cv.PRODUCT_ID',
                    'value' => '3124'
                ]
            ]);
        }
        $product_id_proposalcod_data = \App\Models\ConfigSettings::where('key', 'constants.IcConstants.tata_aig.cv.PRODUCT_ID_PROPOSALCOD')
            ->first();
        if($product_id_proposalcod_data === null)
        {
            DB::table('config_settings')->insert([
                [
                    'label' => 'PRODUCT_ID_PROPOSALCOD',
                    'key' => 'constants.IcConstants.tata_aig.cv.PRODUCT_ID_PROPOSALCOD',
                    'value' => '3121'
                ]
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
