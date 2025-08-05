<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class HyundaiDataUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);
        $files = Storage::allFiles('hyundai_data_upload');
        foreach ($files as $key => $file) {
            $data = file(Storage::path($file));
            $data = str_replace('NULL', null, $data);
            $data = array_map('str_getcsv', $data);
            $header[] = $data[0];
            unset($data[0]);
            $data = array_chunk($data, 100);
            Storage::makeDirectory('hyundai_data_process');
            foreach ($data as $key => $value) {
                $data = array_merge($header, $value);
                $new_file = Storage::path('hyundai_data_process/' . \Illuminate\Support\Str::uuid()->toString() . '.csv');
                $new_file = fopen($new_file, "w");
                foreach ($data as $key => $value) {
                    fputcsv($new_file, $value);
                }
                fclose($new_file);
                HyundaiDataProcess::dispatch();
            }
            \Illuminate\Support\Facades\Storage::delete($file);
        }
        // $files = \Illuminate\Support\Facades\Storage::allFiles('abil-data-migration-uploads-old');
        // foreach ($files as $file) {
        //     $data = \Maatwebsite\Excel\Facades\Excel::toCollection(new \App\Imports\UspImport, $file);
        //     $data = $data[0]->chunk(100);
        //     foreach ($data as $key => $value) {
        //         $value->prepend($value->first()->keys());
        //         \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\DataExport($value->toArray()), 'abibl-data-migration-data-old/' . \Illuminate\Support\Str::random(40) . '.csv', config('filesystems.default'), \Maatwebsite\Excel\Excel::CSV);
        //         AbiblDataMigrationDataJob::dispatch();
        //     }
        //     \Illuminate\Support\Facades\Storage::delete($file);
        // }
    }
}
