<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRoleHasQuickLinkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('role_has_quick_link')) {
                Schema::create('role_has_quick_link', function (Blueprint $table) {
                    $table->integer('role_id')->nullable();
                    $table->unsignedBigInteger('menu_id')->nullable(); 
                    $table->unsignedBigInteger('permission_id')->nullable(); 
                    $table->string('authorization_status')->nullable();
                    $table->timestamps(); // Adds created_at and updated_at
                    $table->foreign('permission_id')
                        ->references('id')->on('permissions')
                        ->onDelete('cascade')
                        ->onUpdate('no action');
                    $table->foreign('menu_id')
                        ->references('menu_id')->on('menu_master')
                        ->onDelete('cascade')
                        ->onUpdate('no action');
                    $table->integer('created_by')->nullable();
                    $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('role_has_quick_link');
    }
}