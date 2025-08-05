<?php

use App\Models\RenewalUpdationLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteRenewalAgentLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $allagentData = DB::table('renewal_agent_updation_logs')->get();
        $insertData = [];
        foreach ($allagentData as $value) {
            $value = json_decode(json_encode($value), true);
            $value['new_data'] = $value['agent_new_data'];
            $value['old_data'] = $value['agent_old_data'];
            $value['type'] = 'agent';
            unset($value['id'], $value['agent_new_data'], $value['agent_old_data']);
            $insertData[] = $value;
        }
        
        if (!empty($insertData)) {
            RenewalUpdationLog::insert($insertData);
        }
        Schema::dropIfExists('renewal_agent_updation_logs');
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
