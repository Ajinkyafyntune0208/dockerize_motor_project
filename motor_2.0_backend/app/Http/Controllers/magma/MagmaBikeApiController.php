<?php

namespace App\Http\Controllers\magma;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ixudra\Curl\Facades\Curl;

class MagmaBikeApiController extends Controller
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/GenerateQuotation')
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/GetIIBClaimDetails')
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/GenerateProposal')
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/ProposalStatus')
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/GeneratePaymentURL')
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

        return Curl::to('https://uatpg.magma-hdi.co.in:444/MHDIWebIntegration/MotorProduct/api/TwoWheeler/GeneratePolicyDocument')
            ->withHeader('Content-type: application/json')
            ->withHeader('Authorization: '.$headers['Authorization'])
            ->withData(json_encode($request->all()))
            ->withTimeout(0)
            ->withConnectTimeout(300)
            ->post();
    }
}
