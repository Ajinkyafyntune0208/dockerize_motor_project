@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

    <p>The OTP for making payment on LifeKaPlan for your {{ ucfirst(strtolower($mailData['product_code'] ?? "" ))}} Insurance policy
        proposal no. {{ $mailData['proposal_number'] }} from
        {{ $mailData['ic_name'] }} for {{ $mailData['vehicale_registration_number'] }}. Period
        {{ $mailData['policy_start_date'] }}-{{ $mailData['policy_end_date'] }}.
        {{ $mailData['ncb'] }}% NCB. Premium Rs. {{ $mailData['final_payable_amount'] }}.</p>

    <h3 style="text-align: center;margin-top:10px;">{{ $mailData['otp'] }}</h3>

    <p style="margin-top:20px;font-size: 0.9rem;font-weight:bold;">For any assistance,
        please feel free to connect us at {{ config('constants.brokerConstant.tollfree_number') }} or drop an
        e-mail at <a
            href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
    </p>
@endsection
