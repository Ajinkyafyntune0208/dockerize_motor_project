<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\IffcoMmvMaster;

class IffcoMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin_lte.iffco_master.index');
    }
    public function getIffcoMasterFile(Request $request)
    {
        if (!empty($request->all()) && ($request->all() != '')  && $request->all()['master_type'] == 'getMMV') {
            try {
                if($request->all()['section'] == 'pcv'){
                $req =  [
                    "contractType" => "CVI",
                    "partnerDetail" => [
                        "partnerCode" => config('constant.IcConstant.Iffco.master_product_partnerCode')
                    ],
                    "master" => [
                        "requestData" => config('constant.IcConstant.Iffco.master_product_requestData')
                    ]
                ];
            }
            else if($request->all()['section'] == 'car'){
                $req =  [
                    "contractType" => "PCP",
                    "partnerDetail" => [
                        "partnerCode" => config('constant.IcConstant.Iffco.master_product_partnerCode_car')
                    ],
                    "master" => [
                        "requestData" => config('constant.IcConstant.Iffco.master_product_requestData_car')
                    ]
                ];
            }
            else if($request->all()['section'] == 'bike'){
                $req =  [
                    "contractType" => "TWP",
                    "partnerDetail" => [
                        "partnerCode" => config('constant.IcConstant.Iffco.master_product_partnerCode_bike')
                    ],
                    "master" => [
                        "requestData" => config('constant.IcConstant.Iffco.master_product_requestData_car')
                    ]
                ];
            }

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('constant.IcConstant.iffco.master_product_url'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($req),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: ' . config('constant.IcConstant.Iffco.master_product_auth_key'),
                        'Content-Type: application/json',
                        'Cookie: ' . config('constant.IcConstant.Iffco.master_product_cookie')
                    ),
                ));
                $resp = curl_exec($curl);
                curl_close($curl);
                $response = json_decode($resp);
                if (empty($response)) {
                    if ($response['result'] === null || $response['result' == '']) {
                        session()->put('message', 'No Data Found');
                        session()->put('iffco-class', 'danger');
                        return redirect()->back();
                    }
                } else {
                    $data = [];
                    $headers = [];
                    $data1 = [];
                    foreach ($response->result as $key => $value) {
                        foreach ($value as $k => $v) {
                            array_push($headers, $k);
                            $headers = array_unique($headers);
                            array_push($data1, $v);
                        }
                        array_push($data, $data1);
                        array_splice($data1, 0, count($data1));
                    }
                    $mmv_master = new IffcoMmvMaster();
                    $mmv_master->ic_name = 'iffco_tokio';
                    $mmv_master->request = json_encode($req);
                    $mmv_master->response = $resp;
                    $mmv_master->save();
                    return Excel::download(new \App\Exports\JsonExport($data, $headers), 'iffco_mmv_' . $request->section . '.xls');
                }
            } catch (\Exception $e) {
                session()->put('message1', 'Something Went Wrong');
                session()->put('iffco-class', 'danger');
                return redirect()->back();
            }
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
