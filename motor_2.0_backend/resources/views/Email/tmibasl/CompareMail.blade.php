@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 1.2rem Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>


        <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;">We bring you a close
            comparison of {{ $mailData["productName"] }} to help you buy a Right Policy. Please find attached the comparison based on
            the following details.</p>

        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;">Registration Date :
            {{ $mailData['reg_date'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;">Previous Expiry
            Date: {{ $mailData['previos_policy_expiry_date'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;">Vehicle model:
            {{ $mailData['vehicle_model'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;">Rto:
            {{ $mailData['rto'] }}</p>

        <p style="margin-top:20px;font-size: 0.9rem;">For any assistance,
            please feel free to connect us at {{ config('constants.brokerConstant.tollfree_number') }} or drop an
            e-mail at <a
                href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
        </p>
    @endsection
