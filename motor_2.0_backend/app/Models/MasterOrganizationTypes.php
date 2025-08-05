<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterOrganizationTypes extends Model
{
    use HasFactory;


    

    public function poi_documents()
    {
        return $this->hasMany(SbiOrganizationDocumentType::class, 'entity_type', 'value')->where(['active' => 'Y', 'document_type' => 'poi'])->select('entity_type', 'document_name', 'document_type');
    }
    public function poa_documents()
    {
        return $this->hasMany(SbiOrganizationDocumentType::class, 'entity_type', 'value')->where(['active' => 'Y', 'document_type' => 'poa'])->select('entity_type', 'document_name', 'document_type');
    }


}
