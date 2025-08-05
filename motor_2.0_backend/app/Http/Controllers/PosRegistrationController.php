<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PosRegistrationController extends Controller
{
    public function posregistration(Request $request)
    {
        $ic_list = $request['ic_list'];
        $return_pos_list = [];
        if(in_array('icici_lombard',$ic_list))
        {
            $return_pos_list['icici_lombard'] = IciciPosRegistration($request);
        } 
        return $return_pos_list;
        //$hdfc='';
        //$hdfc = PosRegistrationHdfc($request);
        if((isset($icic['status']) && $icic['status'] == true) && (isset($hdfc['status']) && $hdfc['status'] == true))
        {
            return [
                'status' => true,
                'message' => 'Pos Registration Done...!'
            ];
        }
        elseif((isset($icic['status']) && $icic['status'] == true))
        {
            return [
                'status' => true,
                'message' => 'Pos Registration Done for ICICI...!'
            ]; 
        }elseif((isset($hdfc['status']) && $hdfc['status'] == true))
        {
            return [
                'status' => true,
                'message' => 'Pos Registration Done HDFC...!'
            ];
        }else{
            return [
                'status' => false,
                'message' => 'Something went Wrong..!'
            ];
        }
    }
}
