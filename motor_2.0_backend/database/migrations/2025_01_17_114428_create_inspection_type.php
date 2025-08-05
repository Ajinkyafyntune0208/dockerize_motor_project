<?php

use App\Models\MasterPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateInspectionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inspection_type', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('company_name');
            $table->enum('Manual_inspection', ['Y', 'N'])->nullable()->default('N');
            $table->enum('Self_inspection', ['Y', 'N'])->nullable()->default('N');
            $table->timestamps();
        });

        $company_id = MasterPolicy::select('insurance_company_id')->distinct()->whereIn('premium_type_id', [4, 6])->where('status','Active')->get()->toArray();
        $com_id=[];
       foreach($company_id as $key => $value)
       {
            foreach($value as $num=>$data)
            {
                $com_id[]= $data;
            }
       }
        $data = DB::table('master_company')->select('company_id','company_alias')->whereIn('company_alias',['liberty_videocon','future_generali'])->whereIn('company_id',$com_id)->get();
        foreach ($data as $company) {
            DB::table('inspection_type')->insert([
                ['company_id'=>$company->company_id,
                'company_name' => $company->company_alias],
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
        Schema::dropIfExists('inspection_type');
    }
}
