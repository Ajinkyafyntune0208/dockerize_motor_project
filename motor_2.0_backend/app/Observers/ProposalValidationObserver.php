<?php

namespace App\Observers;

use App\Models\ProposalValidation as proposal_validation;
use App\Models\userActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ProposalValidationObserver
{

    public $afterCommit = true;
    public function created(proposal_validation $proposalValidation)
    {
        userActivityLog::create([
         'user_id' => auth()->user()->id ?? 0,
            'operation' => 'CREATED',
            'service_type' => 'PROPOSAL VALIDATION',
            'table_name' => $proposalValidation->getTable(),
            'table_primary_id' => $proposalValidation->validation_id,
            'new_data' => json_encode($proposalValidation->toArray()['data']),
        ]);
    }
    public function updated(proposal_validation $proposalValidation)
    {
        
        $olddata = $proposalValidation->getOriginal()['data'];
        $newdata = $proposalValidation->toArray()['data'];
        $originalArray = json_decode($olddata, true);
        $newArray = json_decode($newdata, true);
        $differences = [];
        foreach ($originalArray as $index => $originalItem) {
            $newItem = $newArray[$index];
            foreach ($originalItem as $key => $value) {
                if ($value !== $newItem[$key]) {
                    $differences['original'][$index][$key] = $value;
                    $differences['new'][$index][$key] = $newItem[$key];
                }
            }
        }
        $olddata = !empty($differences['original']) ? json_encode($differences['original']) : null;
        $newdata = !empty($differences['new']) ? json_encode($differences['new']) : null;
        userActivityLog::create([
            'user_id' => auth()->user()->id ?? 0,
            'operation' => 'UPDATED',
            'service_type' => 'PROPOSAL VALIDATION',
            'table_name' => $proposalValidation->getTable(),
            'table_primary_id' => $proposalValidation->validation_id,
            'old_data' => $olddata,
            'new_data' => $newdata,
        ]);
    }
    
}
