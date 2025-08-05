<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\MasterPolicy;
use Illuminate\Support\Facades\DB;

class AlterDataToInspectionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inspection_type', function (Blueprint $table) {
            $company_id = MasterPolicy::select('insurance_company_id')->distinct()->whereIn('premium_type_id', [4, 6])->where('status', 'Active')->get()->toArray();
            $com_id = [];
            foreach ($company_id as $key => $value) {
                foreach ($value as $num => $data) {
                    $com_id[] = $data;
                }
            }
            $data = DB::table('master_company')->select('company_id', 'company_alias')->whereIn('company_id', $com_id)->get();
            if (Schema::hasTable("inspection_type"))  {
                DB::table('inspection_type')->truncate();
            }
            foreach ($data as $company) {
                DB::table('inspection_type')->insert([
                    [
                        'company_id' => $company->company_id,
                        'company_name' => $company->company_alias
                    ],
                ]);
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
        Schema::table('inspection_type', function (Blueprint $table) {});
    }
}
