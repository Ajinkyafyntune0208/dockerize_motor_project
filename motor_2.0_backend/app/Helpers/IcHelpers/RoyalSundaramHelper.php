<?php

    use App\Models\UserProposal;
    use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use Illuminate\Http\Request;

    function getMandatoryDocumentData(UserProposal $proposal)
    {
        $returnData = [
            'panNumber' => '',
            'validatePan' => 'No',
            'uploadForm' => '',
            'form60' => 'No',
            'form49a' => 'No',
            'fileFormat' => '',
            'fileName' => ''
        ];

        if (!empty($proposal->pan_number ?? '')) {
            $returnData['panNumber'] = $proposal->pan_number;
            $returnData['validatePan'] = 'Yes';
        } else {
            $returnData['validatePan'] = 'No';

            $form60File = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . customEncrypt($proposal->user_product_journey_id). '/form60');

            $form49AFile = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . customEncrypt($proposal->user_product_journey_id). '/form49a');

            if (!empty($form60File)) {
                $form60Extension = explode('.', $form60File[0]);
                $form60Extension = end($form60Extension);

                $returnData['form60'] = 'Yes';
                $returnData['fileFormat'] = $form60Extension;
                // $returnData['uploadForm'] = base64_encode(\Illuminate\Support\Facades\Storage::get($form60File[0]));
                $returnData['uploadForm'] = base64_encode(ProposalController::getCkycDocument($form60File[0]));
                $returnData['fileName'] = 'form60.' . $form60Extension;
    
            } else if (!empty($form49AFile)) {
                $form49AExtension = explode('.', $form49AFile[0]);
                $form49AExtension = end($form49AExtension);

                $returnData['form49a'] = 'Yes';
                $returnData['fileFormat'] = $form49AExtension;
                // $returnData['uploadForm'] = base64_encode(\Illuminate\Support\Facades\Storage::get($form49AFile[0]));
                $returnData['uploadForm'] = base64_encode(ProposalController::getCkycDocument($form49AFile[0]));
                $returnData['fileName'] = 'form49a.' . $form49AExtension;
            }
        }

        return $returnData;
    }