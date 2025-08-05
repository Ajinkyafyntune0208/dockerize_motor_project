<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPrevPolicyStartDateTableUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user_proposal', 'prev_policy_start_date')) {
            Schema::table('user_proposal', function (Blueprint $table) {
                $table->text('prev_policy_start_date')->nullable()->after('prev_policy_expiry_date');
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
        //
    }
}
