@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ trim($mailData['name']) }}, </p>

    <div style="font-size:14px">
        <p><b>Greetings from TMIBASL!</b></p>
        <p>Congratulations on getting insured!</p>

        <p>We are pleased to inform you that you can now manage all your insurance policies from various insurers under a
            single log-in account known as Electronic Insurance Account (eIA). Opening an eIA is free. Benefits of an eIA
            include -</p>

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
@endsection
