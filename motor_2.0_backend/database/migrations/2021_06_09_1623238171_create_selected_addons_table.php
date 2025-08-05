<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSelectedAddonsTable extends Migration
{
    public function up()
    {
        Schema::create('selected_addons', function (Blueprint $table) {

		$table->id('id');
		$table->unsignedInteger('user_product_journey_id')->nullable();
		$table->longText('addons')->nullable();
		$table->longText('accessories')->nullable();
		$table->longText('additional_covers')->nullable();
		$table->longText('voluntary_insurer_discounts')->nullable();
        $table->longText('discounts')->nullable();
        $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('selected_addons');
    }
}
