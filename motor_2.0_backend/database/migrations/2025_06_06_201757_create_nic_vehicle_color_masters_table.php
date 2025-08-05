<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('nic_vehicle_color_master')) {
            Schema::create('nic_vehicle_color_master', function (Blueprint $table) {
                $table->id();
                $table->string('color_code', 15)->unique();
                $table->string('color_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nic_vehicle_color_master');
    }
};
