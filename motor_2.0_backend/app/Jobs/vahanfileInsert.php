<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\VahanUploadMigration;
use App\Models\VahanFileLogs;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class vahanfileInsert implements ShouldQueue
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
        $item = VahanFileLogs::where('status', '1')->first();
        if (empty($item))
        return;
        $files = Storage::files($item->file_path);
        $file_id = $item->id;
        $lastIndex = count($files) - 1;
            foreach ($files as $index => $file) {
                if ($index == $lastIndex) {
                    $item->update(['status' => '2']);
                }
                // VahanUploadMigration::dispatch($file, $file_id);
                VahanUploadMigration::dispatch($file, $file_id)->onQueue( "vahan" );
            }   
        }
}
