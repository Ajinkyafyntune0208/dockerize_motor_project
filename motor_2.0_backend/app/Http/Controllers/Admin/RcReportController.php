<?php

namespace App\Http\Controllers\Admin;

use ZipArchive;
use App\Jobs\RcReportJob;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\VahanExportLog;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\FastlaneRequestResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Response as Download;

class RcReportController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = collect();
        // if (!empty($request->rc_number)) {
        //     $data = FastlaneRequestResponse::with([
        //         'agent_details' => function ($query){
        //         return $query->select('user_product_journey_id', 'seller_type');
        //     },
        //     'user_product_journey' => function ($query){
        //         return $query->select('user_product_journey_id', 'sub_source');
        //     }
        //     ])->when(!empty($request->from && $request->to), function ($query) {
        //         $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime(request()->from)))
        //             ->where('created_at', '<', date('Y-m-d 23:59:59', strtotime(request()->to)));
        //     })->when(!empty($request->service_type), function ($query) {
        //         $query->where('transaction_type',  request()->service_type);
        //     })->when(!empty($request->rc_number), function ($query) {
        //         $query->where('request',  request()->rc_number);
        //     })->orderby('id', 'DESC');

        //     if ($request->type == 'excel') {
        //         $data = $data->get();
        //         $records[] = [
        //             "Source",
        //             'Sub Source',
        //             'Enquiry Id',
        //             'Transaction Type',
        //             'Registration No',
        //             'Status',
        //             'Message',
        //             'Manufacturer',
        //             'Model',
        //             'Variant',
        //             'Vehicle Code',
        //             'Request',
        //             'Response',
        //             'Created At',
        //             'Response Time',
        //         ];
        //         foreach ($data as $key => $record) {
        //             $response = json_decode($record['response'], true);

        //             $source = null;
        //             if (!empty($record->agent_details)) {
        //                 if ($record->agent_details->seller_type == 'P') {
        //                     $source = 'POS';
        //                 } else if ($record->agent_details->seller_type == 'E') {
        //                     $source = 'Employee';
        //                 } else {
        //                     $source = $record->agent_details->seller_type;
        //                 }
        //             }else{
        //                 $source = "Non POS";
        //             }
        //             if ($record->transaction_type == "Ongrid Service") {
        //                 $message = '';
        //                 if (isset($response['data']['code']))
        //                     $message = $response['data']['message'] ?? null;
        //                 else
        //                     $message = $record['response'];
        //                 $records[] = [
        //                     'source' => $source,
        //                     'sub_source' => $record->user_product_journey->sub_source,
        //                     'enquiry_id' => $record['enquiry_id'],
        //                     'transaction_type' => $record['transaction_type'],
        //                     'registration_no' => $record['request'],
        //                     'status' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ?  "Success" : "Failed",
        //                     'message' => $message,
        //                     'manufacturer' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? ($response['data']['rc_data']['vehicle_data']['maker_description'] ?? '') : null,
        //                     'model' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? ($response['data']['rc_data']['vehicle_data']['maker_model'] ?? '') : null,
        //                     'variant' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? $response['results'][0]["vehicle"]["fla_variant"] ?? null : null,
        //                     'vehicle_code' => (string) (isset($response['data']['code']) && $response['data']['code'] == '1000') ? $response['data']['rc_data']['vehicle_data']['custom_data']['version_id'] ?? "" : null,
        //                     "request" => (string) $record->request,
        //                     "response" => (string) $record->response,
        //                     'created_at' => $record->created_at,
        //                     'response_time' => $record->response_time,
        //                 ];
        //             } else if ($record->transaction_type == "Fast Lane Service") {
        //                 $message = '';
        //                 if (isset($response['status']))
        //                     $message = $response['description'] ?? null;
        //                 else
        //                     $message = $record->response;
        //                 $records[] = [
        //                     'source' => $source,
        //                     'sub_source' => $record->user_product_journey->sub_source,
        //                     'enquiry_id' => $record->enquiry_id,
        //                     'transaction_type' => $record['transaction_type'],
        //                     'registration_no' => $record->request,
        //                     'status' => (isset($response['status']) && $response['status'] == '100') ?  "Success" : "Failed",
        //                     'message' => $message,
        //                     'manufacturer' => (isset($response['status']) && $response['status'] == '100') ? $response['results'][0]["vehicle"]["fla_maker_desc"] : null,
        //                     'model' => (isset($response['status']) && $response['status'] == '100') ? $response['results'][0]["vehicle"]["fla_model_desc"] : null,
        //                     'variant' => (isset($response['status']) && $response['status'] == '100') ? $response['results'][0]["vehicle"]["fla_variant"] : null,
        //                     'vehicle_code' => (string) (isset($response['status']) && $response['status'] == '100') ? $response['results'][0]["vehicle"]["vehicle_cd"] : null,
        //                     "request" => (string) $record->request,
        //                     "response" => (string) $record->response,
        //                     'created_at' => $record->created_at,
        //                     'response_time' => $record->response_time,
        //                 ];
        //             }
        //         }
        //         return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($records), 'Records.xls');
        //     } else {
        //         $data = $data->paginate(20)->withQueryString();
        //     }
        // }
     
        if(!empty($request->rc_number) || !empty($request->from && $request->to) || !empty($request->service_type) || !empty($request->journey_type )) 
        {
            $data = FastlaneRequestResponse::RcReportData($request->all())->orderby('id', 'DESC');

            if ($request->type == 'excel') {
                $user_details = auth()->user();
                $count = $data->count();
                if($count > 100000){
                    RcReportJob::dispatch($request->all(), $user_details);
                    return Redirect()->back()->with([
                        'status'=> 'Due to large data, we will sent excel files via email.',
                        'class' => 'success'
                    ]);
                }
               
                $data = $data->get();
                $excelFileName = 'file'. time() .'.xls';
                $headings = [
                    'Transaction Type',
                    'Journey Type',
                    'Rc Number',
                    'Response',
                    'Endpoint Url',
                    'Response Time',
                    'Created At',
                ];
                $records[] = $headings;

                foreach ($data as $record) {
                    $rowData = [
                        $record['transaction_type'],
                        $record['type'],
                        $record['request'],
                        $record['response'],
                        $record['endpoint_url'],
                        Carbon::parse($record['response_time'])->format('s'),
                        $record['created_at'],
                    ];
                    $records[] = $rowData;
                }            

                Excel::store(new \App\Exports\DataExport($records), $excelFileName);
                $files[] = $excelFileName;

                $zipFileName = 'VahanExport-'. time() . '.zip';

                $zip = new ZipArchive();
                $zip->open(storage_path($zipFileName), ZipArchive::CREATE);

                foreach ($files as $file) {
                    $fileContent = Storage::disk(config('filesystems.default'))->get($file);
                    $zip->addFromString($file, $fileContent);
                }

                $zip->close();

                Storage::disk(config('filesystems.default'))->put($zipFileName, file_get_contents(storage_path($zipFileName)));

                unlink(storage_path($zipFileName));
                Storage::delete($files); 
            
                $uid = getUUID();
                VahanExportLog::create([
                    'uid'     => $uid,
                    'user_id' => $user_details['id'],
                    'request' => json_encode($request->all()),
                    'file' => $zipFileName,
                    'source' => 'auto-download',
                    'file_expiry' => Carbon::now()->addDays(config('vahanExport.fileExpiry.days'))->format('Y-m-d H:i:s'),
                ]);
                $headers = [
                    'Content-Type'        => 'Content-Type: application/zip',
                    'Content-Disposition' => 'attachment; filename='. $zipFileName
                ];
                return Download::make(Storage::disk(config('filesystems.default'))->get($zipFileName), Response::HTTP_OK, $headers);

            }else {
                $data = $data->paginate(20)->withQueryString();
            }
        }
        return view('rc_report.index', [
            'reports' => $data
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $rc_report = FastlaneRequestResponse::find($id);
        return view('rc_report.show', compact('rc_report'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function proposalValidation(Request $request)
    {
        $records = [];
        if ($request->from && $request->to) {
            $data = \Illuminate\Support\Facades\DB::table('proposal_vehicle_validation_logs')->get()->toArray();
            $data = json_decode(json_encode($data), true);
            foreach ($data as $key => $record) {
                $response = json_decode($record['response'], true);
                if (isset($response['results']['0']['vehicle'])) {
                    if ($key == 0) {
                        $keys = array_keys($response['results']['0']['vehicle']);
                        foreach ($keys as $key => $value) {
                            $records[0][] = Str::title(Str::replace('_', ' ', $value));
                        }
                    }
                    $records[] = $response['results']['0']['vehicle'];
                }
            }
        }
        if ($request->type == 'excel')
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($records), 'Records.xls');
        return view('rc_report.proposal_validation', ['data' => $records]);
    }

    public function download(Request $request, $id)
    {
        $log = FastlaneRequestResponse::find($id);

        if (empty($log)) {
            return response()->json(['message' => 'RC Report not found']);
        }
        $enquiryId = isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : '';
        $text = "Trace ID : " . $enquiryId;
        $text .= "\n\n\nRC Number : " . $log->request ?? '';
        $text .= "\n\n\nTransaction Type : " . $log->transaction_type ?? '';
        $text .= "\n\n\nCreated At : " . (isset($log->created_at) && !empty($log->created_at) ? date('d-M-Y h:i:s A', strtotime($log->created_at)) : '');
        $text .= "\n\n\nRequest URL : \t"  . ($log->endpoint_url ?? '');
        $text .= "\n\n\nRequest : \n\n" . ($log->request ?? '');
        $text .= "\n\n\nResponse : \n\n" . ($log->response ?? '');
        $fileName = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s').'-'.str_replace(' ', '-', $log->transaction_type).'-'.$enquiryId.'.txt');
        return response($text, 200, [
            "Content-Type" => "text/plain",
            'Content-Disposition' => sprintf('attachment; filename="' . $fileName .  '"')
        ]);
    }

    public function validateFile($uid)
    {
        $fileDetails = VahanExportLog::select('file_expiry','file')->where('uid', $uid)->first();

        if(!empty($fileDetails)){
            if($fileDetails['file_expiry'] >= now()){
                // if(file_exists(Storage::path($fileDetails['file'])))
                if(Storage::disk(config('filesystems.default'))->exists($fileDetails['file'])){
                    // return response()->download(Storage::path($fileDetails['file']));
                    $headers = [
                        'Content-Type'        => 'Content-Type: application/zip',
                        'Content-Disposition' => 'attachment; filename='. $fileDetails['file']
                    ];
                return Download::make(Storage::disk(config('filesystems.default'))->get($fileDetails['file']), Response::HTTP_OK, $headers);
                  
                }else{
                    return "File is no longer available.";
                }
            }else{
                return "File is expired";
            }
        }else{
            return "Invalid URL. ";
        }
    }
}
