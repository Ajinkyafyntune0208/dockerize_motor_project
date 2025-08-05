<?php

namespace App\Console\Commands;

use App\Models\VahanExportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VahanExportZipClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VahanExportZipClear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear exported zip files from storage for vahan logs(Rc Report)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $files = VahanExportLog::select('file','id')->where('file_expiry','<', now())->get();

        foreach($files as $file){

            Storage::delete($file->file);

            VahanExportLog::where('id', $file->id)
                ->update([
                    'file_deleted' => '1'
                ]);
        }
    }
}
