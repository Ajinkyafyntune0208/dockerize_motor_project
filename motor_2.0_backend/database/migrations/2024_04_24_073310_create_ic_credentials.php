<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ic_credentials')){
            Schema::create('ic_credentials', function (Blueprint $table) {
                $table->id('ic_credentials_id');
                $table->string('config_name')->default('');
                $table->string('config_key', 255)->default('');
                $table->string('company_alias', 50)->nullable();
                $table->string('section', 100)->nullable();
                $table->string('default_value', 50)->nullable();
                $table->timestamps();
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ic_credentials');
    }
};
