<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRenewFieldsColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('proposal_field', 'renewal_fields')) {
            Schema::table('proposal_field', function (Blueprint $table) {
                $table->text('renewal_fields')->after('fields')->nullable();
                $table->text('fields')->nullable()->change();
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
        Schema::table('proposal_field', function (Blueprint $table) {
            if (Schema::hasColumn('proposal_field', 'renewal_fields')) {
                $table->dropColumn('renewal_fields');
            }
        });
    }
}
