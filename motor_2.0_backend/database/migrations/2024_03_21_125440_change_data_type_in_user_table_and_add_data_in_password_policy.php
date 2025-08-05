<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeDataTypeInUserTableAndAddDataInPasswordPolicy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->timestamp('password_expire_at')->nullable()->default(null)->change();
        });

        DB::table('password_policy')->insert([
            'label' => 'Password policy mail enable',
            'key' => 'password.policy.mail.enable',
            'value' => 'Y',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_table_and_add_data_in_password_policy', function (Blueprint $table) {
            //
        });
    }
}
