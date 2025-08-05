@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 1.1rem Calibri,Candara,Arial;color: #903bf0;margin-bottom: 5px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>
        <p style="margin-top: 2rem;font-size: 0.9rem;">You are just few steps away
            from securing your Vehicle.</p>
        <p style="font-size: 0.9rem;">Our LifeKaPlan team can
            assist you in choosing the Right Policy for your vehicle. </p>
        <p style="font-size: 0.9rem;">Continue your proposal on LifeKaPlan Insurance by clicking <a
                href="{{ $mailData['link'] }}">link</a></p>

        <p style="margin-top:20px;font-size: 0.9rem;">For any
            assistance, please feel free to connect us at
            {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at <a
                href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
        </p>
    @endsection
