@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

    <p style="font-size: 0.9rem;font-weight: bold;"> To create your E Account for Insurance please click
        on below mentioned link, where you can store your all insurance Policies at One Place.</p>

    <p style="font-size: 0.9rem;"><a href="{{ $mailData['link'] }}">link</a></p>

    <p style="margin-top:20px;font-size: 0.9rem;font-weight: bold;">For any
        assistance, please feel free to connect us at
        {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at <a
            href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
    </p>
@endsection
