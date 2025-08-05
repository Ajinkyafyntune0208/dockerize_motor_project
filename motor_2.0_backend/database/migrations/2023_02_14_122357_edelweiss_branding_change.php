<?php

use App\Models\FastlanePreviousIcMapping;
use App\Models\MasterCompany;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EdelweissBrandingChange extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Update master_company
        MasterCompany::where('company_id', 43)->update([
            'company_name' => 'Zuno General Insurance',
            'logo' => 'zuno.png',
        ]);

        // 2. Update master_product
        DB::update(DB::RAW("UPDATE master_product p SET p.product_name = REPLACE(p.product_name, 'Edelweiss', 'Zuno') WHERE p.product_name LIKE '%edelweiss%'"));

        // 3. Update fastlane_previous_ic_mapping
        FastlanePreviousIcMapping::where('company_alias', 'edelweiss')->update(['company_name' => 'Zuno General Insurance Co. Ltd.']);
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
