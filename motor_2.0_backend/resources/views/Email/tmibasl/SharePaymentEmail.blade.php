@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 1.1rem Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

        <p style="margin-top: 2rem;font-size: 0.9rem;"> Please <a
                href="{{ $mailData['link'] }}">Click here</a> to pay the premium for your vehicle policy
            {{ $mailData['vehicale_registration_number'] }}. Proposal No. {{ $mailData['proposal_no'] }}.</p>

        <p style="margin-top: 0.5rem;font-size: 0.9rem;"> Your Total Payable Amount
            is Rs. {{ $mailData['final_amount'] }}.</p>

        {{-- <p style="font-size: 0.9rem;">Continue your payment on {{ config('app.brokername') }} Insurance by
            clicking <a href="{{ $mailData['link'] }}">link.</a>
        </p> --}}

        <p style="margin-top: 0.5rem;font-size: 0.9rem;"> Important: This link will expire at {{ date('d F Y') . ' 23.59.' }}</p>
        <p style="font-size: 0.9rem;">Enjoy a safe drive!</p>

        <p style="margin-top: 20px;font-size: 0.9rem;letter-spacing: 0.3px;">For any
            assistance, please feel free to connect us at
            {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at <a
                href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
        </p>
    @endsection
