<?php

namespace App\Http\Controllers\magma;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ixudra\Curl\Facades\Curl;

class MagmaCarApiController extends Controller
{
    function tokenGeneration(Request $request)
    {
        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/token')
            ->withHeader('Content-type: application/x-www-form-urlencoded')
            ->withData(http_build_query($request->all()))
            ->post();
    }

    function premiumCalculation(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/GenerateQuotation')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }

    function iibVerification(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/GetIIBClaimDetails')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }

    function proposalGeneration(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/GenerateProposal')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }

    function proposalStatus(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/ProposalStatus')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }    

    function pgRedirection(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/GeneratePaymentURL')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }

    function policyGeneration(Request $request)
    {
        $headers = getallheaders();

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/PrivateCar/GeneratePolicyDocument')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }
}
