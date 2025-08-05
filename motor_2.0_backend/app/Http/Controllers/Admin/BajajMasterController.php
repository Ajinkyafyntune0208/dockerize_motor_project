<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class BajajMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('bajaj_master.index');
    }
    public function getBajajFile(Request $request)
    {
        try {
            if ($request->section == 'bike') {
                $productCode = config('constants.motor.bajaj_allianz.PRODUCT_CODE_BAJAJ_ALLIANZ_BIKE');
                $userId = config('constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE');
                $password = config('constants.motor.bajaj_allianz.AUTH_PASS_BAJAJ_ALLIANZ_BIKE');
            } elseif ($request->section == 'car') {
                $productCode = config('constants.motor.bajaj_allianz.PRODUCT_CODE_BAJAJ_ALLIANZ_MOTOR');
                $userId = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME');
                $password = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PASSWORD');
            }
            $response = Http::post(config('constants.IcConstants.bajaj.BAJAJ_MASTER_URL'), [
                'userid' => $userId,
                'password' => $password,
                'productcode' => $productCode,
            ]);
            if ($response['vehiclemasterlist'] === null) {
                session()->put('message', 'No Data Found');
                session()->put('bajaj-class', 'danger');
                return redirect()->back();
            }
            $data = $response['vehiclemasterlist'];
            $headers = array_keys($data[0]);
            return Excel::download(new \App\Exports\JsonExport($data, $headers), 'bajaj_mmv_' . $request->section . '.xls');
        } catch (\Exception $e) {
            session()->put('message', 'Something Went Wrong');
            session()->put('bajaj-class', 'danger');
            return redirect()->back();
        }
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
        //
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
        //
    }
}
