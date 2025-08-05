<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnProductNameProductIdentifierTableMasterPolicy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('master_policy', 'product_name')) {
            Schema::table('master_policy', function (Blueprint $table) {
                $table->string('product_unique_name',200)->nullable()->after('business_type');
                $table->string('product_name',200)->nullable()->after('product_unique_name');
                $table->string('product_identifier',100)->nullable()->after('product_name');
                $table->text('product_description')->nullable();
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
