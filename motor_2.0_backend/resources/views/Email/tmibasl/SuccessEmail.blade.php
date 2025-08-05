@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ trim($mailData['name']) }}, </p>

    <div style="font-size:14px">

        <p><b>Greetings from TMIBASL!</b></p>
        <p>Congratulations on the purchase of your policy!</p>
        <p>Please find attached your insurance policy no. <b>{{ $mailData['policy_number'] }}</b>.</p>
        <p>Kindly read the policy document carefully and retain it safely in digital or print format. We urge you to revert
            to
            us within 15 days in case of any discrepancy.</p>

        <p>For further assistance you may call our Toll-Free No: <b>{{ config('constants.brokerConstant.tollfree_number') }}</b>
        </p>

        <p>You can now manage all your insurance policies under a single log-in account known as Electronic Insurance
            Account
            (eIA). Opening an eIA is free. Benefits of an eIA include -</p>

        <ul>
            <li>View and manage all your insurance policies under a single account</li>
            <li>No more hassle of maintaining physical policy documents</li>
            <li>Once KYC documents are submitted and an eIA is opened, you do not have to resubmit KYC documents</li>
            <li>Changes in contact details get auto updated in all your policies across various insurers</li>
            <li> To open an eIA please click on the below button:</li>
        </ul>

        <a href="{{ $mailData['nsdlUrl'] }}">
            <button style="background:#0099f2;border-radius: 4px;padding:10px;color: #fff;border: none; margin-top:10px;">
                Click Here
            </button>
        </a>

        <p>You will be redirected to the NSDL Database Management Limited website for opening an eIA. Kindly keep your PAN
            or
            Aadhaar card handy for hassle free journey.</p>
    </div>


    {{-- <p style="margin-top: 30px;font-size: 0.9rem;font-weight: bold;letter-spacing: 0.3px;">For any
        assistance, please feel free to connect us at
        {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at <a
            href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
    </p> --}}
@endsection
