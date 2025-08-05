<?php

namespace App\Jobs;

use App\Models\UserProposal;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use App\Models\CvJourneyStages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\CkycController;
use Exception;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SbiDocumentUploadFailureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /*
     * Create a new job instance.
     *
     * @return void
     */
    protected $enquiry_id;

    public function __construct()
    {
    }
    /**
     * Execute the job.
     *
     * @return void
     */


    public function handle()
    {
        $failedEntries = $this->getFailedEntries();

        $response = [];
        foreach($failedEntries as $entry) {
            $proposal = UserProposal::where('user_product_journey_id', $entry->user_product_journey_id)->first();

            $response[] = $this->ckycService(new CkycController, $proposal);

            // dd($response);
        }

        Log::info("SBI document upload job: ", $response);
        
        echo json_encode($response);
        header("Content-Type: application/json");
        die();
    }

    public function getFailedEntries()
    {
        return CvJourneyStages::join('user_proposal as up', 'up.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->join('proposer_ckyc_details as cd', 'cd.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->whereIn('cv_journey_stages.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
            ->where('up.ic_id', 34)
            ->where('up.is_ckyc_verified', 'N')
            ->where('cd.is_document_upload', 'Y')
            ->where(function ($query) {
                $query->whereNull('ckyc_meta_data');
                $query->orWhere('ckyc_meta_data', 'not like', '%file_upload%');
                $query->orWhereJsonDoesntContain('ckyc_meta_data->file_upload', true);
            })
            ->limit(50)
            ->get();
    }
    
    function ckycService(CkycController $ckycController, UserProposal $proposal)
    {
        try{
            $ckycVerification = $ckycController->ckycVerifications(new Request([
                'companyAlias' => 'sbi',
                'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                'mode' => 'documents'
            ]));

            if ($ckycVerification->status() == 200) {
                $ckycVerificationResponse = $ckycVerification->getOriginalContent();

                if ($ckycVerificationResponse['status']) {
                    return [
                        'status' => true,
                        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    ];
                } else {
                    if($ckycVerificationResponse['data']['meta_data']['file_upload'] ?? false) {
                        $ckyc_meta_data = json_decode($proposal->ckyc_meta_data ?? '', 1);
                        $ckyc_meta_data['file_upload'] = $ckycVerificationResponse['data']['meta_data']['file_upload'];

                        UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->update([
                            'ckyc_meta_data' => json_encode($ckyc_meta_data)
                        ]);
                        return [
                            'status' => true,
                            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                            'data' => [
                                'file_upload' => $ckycVerificationResponse['data']['meta_data']['file_upload']
                            ]
                        ];
                    }
                    return [
                        'status' => false,
                        'message' => $ckycVerificationResponse['data']['message'] ?? 'CKYC verification failed',
                        'msg' => $ckycVerificationResponse['data']['message'] ?? 'CKYC verification failed',
                        'ckycVerification' => $ckycVerificationResponse
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                    'message' => 'Unable to upload documents, please try again after some time.'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'enquiryId' => customEncrypt($proposal->user_product_journey_id),
                'message' => 'Unable to upload documents, please try again after some time.',
                'errorMessage' => $e->getMessage() . ' File:- ' . $e->getFile() . ' Line:-  ' . $e->getLine()
            ];
        }
    }

}