<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class AgentMigrationController extends Controller
{
    public function migrateAgent(Request $request)
    {
        if (config('constants.motorConstant.AGENT_MIGRATION_ENABLED') != 'Y') {
            return response()->json([
                'status' => false,
                'message' => 'Agent migration is disabled',
            ], 422);
        }

        $rules = [
            'seller_type' => 'required|in:P,U,E,MISP,PARTNER',
        ];
        
        if (in_array($request->seller_type, ['P', 'E', 'MISP', 'PARTNER'])) {
            $rules['agent_id'] = 'required';
            $rules['new_data.agent_id'] = 'required';
        } elseif (in_array($request->seller_type, ['U'])) {
            $rules['user_id'] = 'required';
            $rules['new_data.user_id'] = 'required';
        }
        
        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $logs = [];

        if (in_array($request->seller_type, ['U'])) {
            \App\Models\CvAgentMapping::where('user_id', $request->user_id)
                ->where(function ($query) {
                    $query->whereIn('seller_type', ['U', 'B2C'])
                        ->orWhereNull('seller_type');
                })
            ->orderBy('id')
            ->chunkById(100, function ($records) use (&$logs, $request) {
                $log = $this->migrateRecords($records, [
                    'user_id' => $request->new_data['user_id'],
                ], $request);
                $logs = array_merge($logs, $log);
            });
        } else {
            \App\Models\CvAgentMapping::where([
                'seller_type' => $request->seller_type,
                'agent_id' => $request->agent_id,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($records) use (&$logs, $request) {
                $log = $this->migrateRecords($records, [
                    'agent_id' => $request->new_data['agent_id'],
                ], $request);
                $logs = array_merge($logs, $log);
            });
        }

        if (empty($logs)) {
            return response()->json([
                'status' => false,
                'message' => 'No records found to migrate',
            ], 422);
        }

        \App\Models\AgentUpdationLog::insert($logs);

        return response()->json([
            'status' => true,
            'message' => 'Agent migration successful',
            'records_updated' => count($logs),
        ], 201);
    }

    private function migrateRecords($records, $updateFields, $request)
    {
        $logs = [];
        foreach ($records as $record) {
            $original = $record->replicate();

            if (
                empty($record->seller_type) &&
                in_array($request->seller_type, [
                    'U',
                    'B2C'
                ])
            ) {
                $updateFields['seller_type'] = 'b2c';
            }

            $record->update($updateFields);

            \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                'enquiryId' => $record->user_product_journey_id,
            ]), $record->user_product_journey_id, false);

            $newData = $record->getChanges();
            unset($newData['updated_at']);
            $oldData = array_intersect_key($original->getAttributes(), array_flip(array_keys($newData)));

            $logs[] = [
                'user_product_journey_id' => $record->user_product_journey_id,
                'cv_agent_mapping_id' => $record->id,
                'request_payload' => json_encode($request->input()),
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($newData),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $logs;
    }
}
