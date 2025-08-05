@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 1.2rem Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

        <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;">Please find attached premium
            break-up of your selected {{ $mailData['productName'] }} quote.
        </p>
        <p style="margin-top: 20px;font-size: 0.9rem;">For any
            assistance, please feel free to connect us at
            {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at <a
                href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
        </p>
@endsection
