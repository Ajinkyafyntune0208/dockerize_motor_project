    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyInSelectedAddonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('selected_addons', function (Blueprint $table) {
            $table->bigInteger('user_product_journey_id')->change();
            $table->foreign('user_product_journey_id','selected_addons_journey_id_foreign')->references('user_product_journey_id')->on('user_product_journey');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('selected_addons', function (Blueprint $table) {
            //
        });
    }
}
