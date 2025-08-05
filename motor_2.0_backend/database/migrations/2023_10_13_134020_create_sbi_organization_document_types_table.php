<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSbiOrganizationDocumentTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 'entity_type', 'document_name', 'document_type', 'active'
        Schema::create('sbi_organization_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->nullable();
            $table->string('document_name')->nullable();
            $table->set('document_type', ['poi', 'poa'])->nullable();
            $table->string('active')->default('N');
            $table->timestamps();
        });
        Artisan::call('db:seed --class=InsertSbiOrganizationDocumentTypesData');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('sbi_organization_document_types');
    }
}
