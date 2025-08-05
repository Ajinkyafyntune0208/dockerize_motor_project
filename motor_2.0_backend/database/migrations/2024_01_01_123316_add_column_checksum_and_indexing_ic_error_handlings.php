<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnChecksumAndIndexingIcErrorHandlings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('ic_error_handlings', 'checksum')) {
            Schema::table('ic_error_handlings', function (Blueprint $table) {
                $table->longText('checksum')->nullable()->after('section');
                $table->fullText('checksum');
                $table->index(['company_alias', 'section', 'status'], 'ic_error_handlings_multiple_index');
            });
        }

        if (!Schema::hasColumn('proposal_ic_error_handlings', 'checksum')) {
            Schema::table('proposal_ic_error_handlings', function (Blueprint $table) {
                $table->longText('checksum')->nullable()->after('section');
                $table->fullText('checksum');
                $table->index(['company_alias', 'section', 'status'], 'proposal_ic_error_handlings_multiple_index');
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
        if (Schema::hasColumn('ic_error_handlings', 'checksum')) {
            Schema::table('ic_error_handlings', function (Blueprint $table) {
                $table->dropColumn('checksum');
                $table->dropIndex('ic_error_handlings_multiple_index');
            });
        }

        if (Schema::hasColumn('proposal_ic_error_handlings', 'checksum')) {
            Schema::table('proposal_ic_error_handlings', function (Blueprint $table) {
                $table->dropColumn('checksum');
                $table->dropIndex('proposal_ic_error_handlings_multiple_index');
            });
        }
    }
}
