<?php

namespace App\Http\Controllers\GenerateQR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\QrLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use QrCode;
use Razorpay\Api\QrCode as ApiQrCode;

class QRCodeController extends Controller
{
    public function generateQRCode(Request $request)
    {

        try {
            $link = $request->url_link;

            $qrCode = QrCode::size(250)->generate($link);

            if ($qrCode) {

                $folder = 'public/QrCodes';

                $existingQrLink = QrLink::where('enquiry_id', $request->enquiryId)->where('qr_type', $request->qr_type)->first();
                if ($existingQrLink) {
                    $filename = md5($existingQrLink->id) . '.svg';
                    // $filePath = Storage::disk('local')->url($folder . '/' . $filename);
                    // Storage::disk('local')->put($folder . '/' . $filename, $qrCode);
                    Storage::put($folder . '/' . $filename, $qrCode);
                    DB::table('qr_links')
                        ->where('id', $existingQrLink->id)
                        ->update([
                            'qr_image_path' => $folder . '/' . $filename,
                            'updated_at' => Carbon::now(),
                        ]);

                    return [
                        'status' => true,
                        'qr_link' => file_url($folder . '/' . $filename),
                        'msg' => 'QR Code Generated'
                    ];
                }

                $qrLink = new QrLink;
                $qrLink->enquiry_id = $request->enquiryId;
                $qrLink->qr_type = $request->qr_type;
                $qrLink->save();
                $filename = md5($qrLink->id) . '.svg';
                // $filePath = Storage::disk('local')->url($folder . '/' . $filename);
                // Storage::disk('local')->put($folder . '/' . $filename, $qrCode);
                Storage::put($folder . '/' . $filename, $qrCode);
                $qrLink->qr_image_path = $folder . '/' . $filename;
                $qrLink->save();
                return [
                    'status' => true,
                    'qr_link' => file_url($folder . '/' . $filename),
                    'msg' => 'QR Code Generated'
                ];
            }
            return [
                'status' => false,
                'qr_link' => '',
                'msg' => 'QR Code Not Generated'
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'msg'    => $e->getMessage()
            ];
        }
    }
}
