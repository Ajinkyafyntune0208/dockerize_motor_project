<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\VahanFileLogs;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class VahanChunkFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 7200;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);
        $currentMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1800);

        try {
            $fileLog = VahanFileLogs::where('status', '0')->first();
            if (!$fileLog) {
                $sourcePath = config('EXTERNAL.VAHAN_FILE_UPLOAD.PATH'); #server path
                $files = glob($sourcePath . DIRECTORY_SEPARATOR . '*.json');  #check the file
                if (empty($files)) {
                    return;
                }
                foreach ($files as $sourceFile) {
                    $originalFilename = basename($sourceFile); // e.g. speed.json
                    $filenameWithoutExtension = pathinfo($originalFilename, PATHINFO_FILENAME); // e.g. speed

                    $relativePath = "vahan_import/{$filenameWithoutExtension}/{$originalFilename}";
                    $destinationDir = "vahan_import/{$filenameWithoutExtension}";

                    if (!Storage::exists($destinationDir)) {
                        Storage::makeDirectory($destinationDir, 0777, true);
                        Storage::put($relativePath, file_get_contents($sourceFile));

                        VahanFileLogs::create([
                            'file_path' => $relativePath,
                            'file_name' => $originalFilename,
                        ]);
                    }
                }
            } else {
                $filePath = $fileLog->file_path;
                $originalFileName = $fileLog->file_name;
                $jsonData = json_decode(Storage::get($filePath), true);
                if (empty($jsonData)) {
                    Log::info("Given Json file {$fileLog->file_name} is not validate json.");
                    return;
                }
                $totalCount = count($jsonData);
                $chunks = array_chunk($jsonData, 500, true);

                $folderName = 'vahan_import/' . pathinfo($originalFileName, PATHINFO_FILENAME);
                if (!Storage::exists($folderName)) {
                    Storage::makeDirectory($folderName);
                }

                foreach ($chunks as $index => $chunk) {
                    $name = Str::uuid();
                    $subFilePath = $folderName . '/' . $index . '_' . $name . ".json";
                    Storage::put($subFilePath, json_encode($chunk));
                }

                $fileLog->update([
                    'file_path' => $folderName,
                    'file_name' => $fileLog->file_name,
                    'status' => '1',
                    'total_count' => $totalCount
                ]);
                Storage::delete($filePath);
            }

            ini_set('memory_limit', $currentMemoryLimit);
        } catch (\Exception $e) {

            ini_set('memory_limit', $currentMemoryLimit);
        }
    }
}
