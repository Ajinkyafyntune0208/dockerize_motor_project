<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VahanUplordLogs;
use Illuminate\Support\Facades\Storage;
use App\Models\VahanFileLogs;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VahanUploadExport;
use App\Models\User;
use App\Models\VahanImportExcelLogs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class VahanUpload extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('vahan_upload.list')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->has('start_date') && $request->has('end_date')) {

            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $userEmail = auth()->user()->email;

            $data = VahanUplordLogs::whereBetween('created_at', [$start_date, $end_date])
                ->select('vehicle_reg_no', 'response', 'created_at')
                ->count();

            if ($data > 1000) {

                $getUniqueId = Str::uuid();
                VahanImportExcelLogs::create([
                    'unique_id' => $getUniqueId,
                    'user_email' => $userEmail,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ]);

                return redirect()->back()->with('success', 'Your request has been submitted. You will receive an email shortly.');
            }

            return Excel::download(new VahanUploadExport($start_date, $end_date) , 'Vahan Report.xls');
        }

        $logs = VahanFileLogs::orderBy('id', 'desc')->get();
        return view('admin_lte.vahan_upload.index', compact('logs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jsonfile' => 'required|file',
            ]);
    
            if ($validator->fails()) {
                return redirect()->route('admin.vahan-upload.index')->with([
                    'status' => 'Please upload a JSON file',
                    'class' => 'danger',
                ]);
            }

            $file = $request->file('jsonfile');
            $originalFileName = $file->getClientOriginalName();
            $filePath = $file->storeAs('vahan_import/' . pathinfo($originalFileName, PATHINFO_FILENAME), $originalFileName);

            VahanFileLogs::create([
                'file_path' => $filePath,
                'file_name' => $originalFileName,
            ]);

            return redirect()->route('admin.vahan-upload.index')->with([
                'status' => 'File uploaded and processing started.',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->route('admin.vahan-upload.index')->with([
                'status' => 'Something went worng.',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = VahanFileLogs::find($id);
        if ($data->status == 0) {
            Storage::delete('public/vahan_upload/' . $data->file_id . '.json');
        }
        $data->delete();
        return redirect()->route('admin.vahan-upload.index')->with([
            'status' => 'Deleted suceesfully',
            'class' => 'success',
        ]);
    }

    public function downloadVahanExcel(Request $request)
    {
        $token = $request->query('token');

        $decoded_token = base64_decode($token);
        $token_parts = explode('|', $decoded_token);

        $emailPart = $token_parts[0];
        $validity = $token_parts[1];
        $uui = $token_parts[2];


        $currentDate = Carbon::now()->timestamp;

        $email = filter_var(base64_decode($emailPart), FILTER_VALIDATE_EMAIL) ? base64_decode($emailPart) : $emailPart;
        $user = User::where('email', $email)->first();

        $Exceldata = VahanImportExcelLogs::where('unique_id', $uui)->first();
        if ($user->email === $email && ($currentDate <= $validity)) {

            if(!$Exceldata) return response()->json(['message' => 'No Files Found for this link , Kindly try Again..!!'], 404); ;
            return response()->download(Storage::path($Exceldata->file_path) , 'Vahan Report.xlsx' );
 
        } else {
            Storage::delete($Exceldata->file_path);
            return response()->json(['message' => 'This download link has expired'], 404);
        }
    }
}