<?php

namespace App\Console\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClearExpiredCkycPhotos extends Command
{
    protected $signature = 'clear:expired_ckyc_photos';
    protected $description = 'Delete ckyc photos older directories';

    public $directories = [];
    public $deleted_directory = [];
    public $expirationTime = null;

    public function handle()
    {
        $this->checkRequestValidity();
        $this->getCurrentDirectories();
        $this->setExpirationTime();

        foreach ($this->directories as $directory) {
            $directory_path_array = explode('/', $directory);
            if (!(count($directory_path_array) >= 2 && $directory_path_array[0] == 'ckyc_photos' && !empty($directory_path_array[1]))) {
                continue;
            }

            $modificationTime = $this->getLastModifiedTime($directory);
            $currentDirectoryArray = ['expirationTime' => $this->expirationTime->timestamp];
            $status = '';

            if (!empty($modificationTime) && $modificationTime < $this->expirationTime->timestamp) {
                Storage::deleteDirectory($directory);
                $status = 'deleted';
            }
            $currentDirectoryArray['modificationTime'] = $modificationTime;
            $currentDirectoryArray['status'] = $status;

            $this->deleted_directory[$directory] = $currentDirectoryArray;
        }
        $this->closeClearCommand();
    }

    function checkRequestValidity()
    {
        if (!(config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_PHOTOS_DELETION_ENABLED') == 'Y')) {
            Log::info("directories deletion is not allowed: ", []);
            exit();
        }
    }

    function setExpirationTime()
    {
        $this->expirationTime = Carbon::now()->subDays(config('constants.IS_CKYC_PHOTOS_DELETION_DELAY', 7));
    }

    function getCurrentDirectories()
    {
        $directories = Storage::directories('ckyc_photos');
        $this->directories = array_chunk($directories, 500)[0] ?? [];
    }

    function getLastModifiedTime($directory)
    {
        $files = Storage::allFiles($directory);
        $last_modified_on = 0;

        foreach ($files as $file) {
            $current_last_modified = Storage::lastModified($file);
            if ($current_last_modified > $last_modified_on) $last_modified_on = $current_last_modified;
        }
        return $last_modified_on;
    }

    function closeClearCommand()
    {
        Log::info("Deleted directories: ", ['deleted_directory' => $this->deleted_directory, 'directories' => $this->directories]);
        echo json_encode(['deleted_directory' => $this->deleted_directory, 'directories' => $this->directories]);
        // header('Content-Type: application/json');
        exit();
    }
}
