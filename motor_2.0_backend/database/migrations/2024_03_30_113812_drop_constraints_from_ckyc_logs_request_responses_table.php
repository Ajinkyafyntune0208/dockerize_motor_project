<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropConstraintsFromCkycLogsRequestResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (app()->environment('local')) {
            Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
                $this->dropConstraintIfExists('ckyc_logs_request_responses', 'ckyc_logs_request_responses_chk_1');
                $this->dropConstraintIfExists('ckyc_logs_request_responses', 'ckyc_logs_request_responses_chk_2');
                $this->dropConstraintIfExists('ckyc_logs_request_responses', 'ckyc_logs_request_responses_chk_3');
            });

            /*
                -- Dropping check constraints
                ALTER TABLE ckyc_logs_request_responses DROP CONSTRAINT IF EXISTS ckyc_logs_request_responses_chk_1;
                ALTER TABLE ckyc_logs_request_responses DROP CONSTRAINT IF EXISTS ckyc_logs_request_responses_chk_2;
                ALTER TABLE ckyc_logs_request_responses DROP CONSTRAINT IF EXISTS ckyc_logs_request_responses_chk_3;
            */
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ckyc_logs_request_responses', function (Blueprint $table) {
            //
        });
    }

    protected function dropConstraintIfExists($table, $constraintName)
    {
        $sql = "SELECT COUNT(*) AS count FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_name = ?";
        $count = DB::selectOne($sql, [$constraintName])->count;

        if ($count > 0) {
            DB::statement("ALTER TABLE $table DROP CONSTRAINT $constraintName");
        }
    }
}
