<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnRelationTypeInUserProductJourney extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_product_journey', function (Blueprint $table) {
            if ( ! Schema::hasColumn('user_product_journey', 'relationship_type')) {
                $table->string('relationship_type', 20)->nullable()->after('user_mname');
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
        Schema::table('user_product_journey', function (Blueprint $table) {
            if (Schema::hasColumn('user_product_journey', 'relationship_type')) {
                $table->dropColumn('relationship_type');
            }
        });
    }
}
