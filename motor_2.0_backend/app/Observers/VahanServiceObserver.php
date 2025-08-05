<?php

namespace App\Observers;

use App\Models\userActivityLog;
use App\Models\VahanService;

class VahanServiceObserver
{
    /**
     * Handle the VahanService "created" event.
     *
     * @param  \App\Models\VahanService  $vahanService
     * @return void
     */
    public function created(VahanService $vahanService)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'CREATED',
            'service_type' => 'VAHAN SERVICE',
            'table_name' => $vahanService->getTable(),
            'table_primary_id' => $vahanService->id,
            'new_data' => json_encode($vahanService->toArray()),
        ]);
    }

    /**
     * Handle the VahanService "updated" event.
     *
     * @param  \App\Models\VahanService  $vahanService
     * @return void
     */
    public function updated(VahanService $vahanService)
    {
        $differenceKeys = collect(array_keys(array_diff($vahanService->getOriginal(), $vahanService->toArray())));
        $old_data = $new_data = [];
        $differenceKeys->each(function ($key) use (&$old_data, &$new_data, $vahanService) {
            $old_data[$key] = $vahanService->getOriginal()[$key] ?? null;
            $new_data[$key] = $vahanService->toArray()[$key] ?? null;
        });

        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'UPDATED',
            'service_type' => 'VAHAN SERVICE',
            'table_name' => $vahanService->getTable(),
            'table_primary_id' => $vahanService->id,
            'old_data' => json_encode($old_data),
            'new_data' => json_encode($new_data),
        ]);
    }

    /**
     * Handle the VahanService "deleted" event.
     *
     * @param  \App\Models\VahanService  $vahanService
     * @return void
     */
    public function deleted(VahanService $vahanService)
    {
        userActivityLog::create([
            'user_id' => auth()->user()->id,
            'operation' => 'DELETED',
            'service_type' => 'VAHAN SERVICE',
            'table_name' => $vahanService->getTable(),
            'table_primary_id' => $vahanService->id,
            'old_data' => json_encode($vahanService->getOriginal()),
        ]);
    }

    /**
     * Handle the VahanService "restored" event.
     *
     * @param  \App\Models\VahanService  $vahanService
     * @return void
     */
    public function restored(VahanService $vahanService)
    {
        //
    }

    /**
     * Handle the VahanService "force deleted" event.
     *
     * @param  \App\Models\VahanService  $vahanService
     * @return void
     */
    public function forceDeleted(VahanService $vahanService)
    {
        //
    }

}
