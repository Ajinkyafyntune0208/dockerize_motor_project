<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lead_generation_logs', function (Blueprint $table) {
            $table->index('enquiry_id');
            $table->index('method');
            $table->index(['enquiry_id', 'method', 'created_at'], 'idx_enquiry_method_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lead_generation_logs', function (Blueprint $table) {
            //
        });
    }
}
