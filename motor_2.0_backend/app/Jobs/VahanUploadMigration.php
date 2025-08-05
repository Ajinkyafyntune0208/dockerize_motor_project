<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\FastlaneRequestResponse;
use App\Models\VahanUplordLogs;
use Illuminate\Support\Carbon;
use App\Models\RegistrationDetails;
use App\Models\VahanFileLogs;
use Illuminate\Support\Facades\DB;

class VahanUploadMigration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $file;
    public $file_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file , $file_id)
    {
        $this->file = $file;
        $this->file_id = $file_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0); 
        ini_set('memory_limit', '-1');
        $data = json_decode(Storage::get($this->file), true);

        if (empty($data)) {
            Storage::delete($this->file);
            return;
        }
        $insertLogs = [];
        $insertFastlaneRequestResponse = [];
        $insertRegistrationDetails = [];
        $processed_count = 0;
        $exisiting_count = 0;

        $vehicleNumbers = [];

        foreach ($data as $key => $value) {
            if (empty($value)) continue;

            $vehicle_reg_no = empty($key) ? ($value['essentials']['vehicleNumber'] ?? null) : $key;
            if (!$vehicle_reg_no) continue;

            $vehicle_reg_no = getRegisterNumberWithHyphen(trim($vehicle_reg_no));
            $vehicleNumbers[$vehicle_reg_no] = true;
        }

        $existingResponsesRaw = VahanUplordLogs::whereIn('vehicle_reg_no', array_keys($vehicleNumbers))
            ->pluck('response', 'vehicle_reg_no')
            ->toArray();

        foreach ($data as $key => $value) {
            if (empty($value)) continue;

            $vehicle_reg_no = empty($key) ? ($value['essentials']['vehicleNumber'] ?? null) : $key;
            if (!$vehicle_reg_no) continue;

            $vehicle_reg_no = getRegisterNumberWithHyphen(trim($vehicle_reg_no));
            if (!$vehicle_reg_no) continue;

            $existingResponseRaw = $existingResponsesRaw[$vehicle_reg_no] ?? null;
            $existingResponse = $existingResponseRaw ? md5($existingResponseRaw) : null;
            $currentRecord = md5(json_encode($value));

            if ($existingResponse !== $currentRecord) {
                $timestamp = now();

                $insertFastlaneRequestResponse[] = [
                    'request' => $vehicle_reg_no,
                    'response' => json_encode($value),
                    'source' => 'Offline',
                    'created_at' => $timestamp
                ];

                $insertRegistrationDetails[] = [
                    'vehicle_reg_no' => $vehicle_reg_no,
                    'vehicle_details' => json_encode($value),
                    'source' => 'Offline',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'expiry_date' => !empty($value['result']['vehicleInsuranceUpto'])
                        ? Carbon::parse(str_replace('/', '-', $value['result']['vehicleInsuranceUpto']))->format('Y-m-d')
                        : null,
                ];

                $insertLogs[] = [
                    'vehicle_reg_no' => $vehicle_reg_no,
                    'source' => 'Offline',
                    'response' => json_encode($value),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                // $processed_count++;
            } else {
                $exisiting_count++;
            }
        }
        if (!empty($insertLogs)) {
            foreach (array_chunk($insertLogs, 100) as $chunk) {
                VahanUplordLogs::insert($chunk);
                $processed_count += count($chunk);
            }

            foreach (array_chunk($insertRegistrationDetails, 100) as $chunk) {
                RegistrationDetails::insert($chunk);
            }

            foreach (array_chunk($insertFastlaneRequestResponse, 100) as $chunk) {
                FastlaneRequestResponse::insert($chunk);
            }

            VahanFileLogs::where('id', $this->file_id)->update([
                'processed_count' => DB::raw("processed_count + $processed_count")
            ]);
        }

        if ($exisiting_count > 0) {
            VahanFileLogs::where('id', $this->file_id)->update([
                'exisiting_count' => DB::raw("exisiting_count + $exisiting_count"),
            ]);
        }

        Storage::delete($this->file);
    }
}
