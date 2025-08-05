<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class DeleteOldVahanExcelFiles implements ShouldQueue
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
        //
        $directory = 'vahan_excel_import';

        $files = Storage::files($directory);


        if (!empty($files)) {

            foreach ($files as $file) {

                $lastModified = Storage::lastModified($file);

                $fileAgeInSeconds = Carbon::now()->timestamp - $lastModified;

                if ($fileAgeInSeconds > 3600) {

                    Storage::delete($file);
                }
            }
        }
    }
}