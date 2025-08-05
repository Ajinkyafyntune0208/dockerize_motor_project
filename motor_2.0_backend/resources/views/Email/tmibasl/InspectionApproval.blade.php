@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

        <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;"> Your Inspection request with
            {{ $mailData['insurer'] }} for vehicle reg no. is approved. Kindly click on the link
            {{ $mailData['payment_url'] }} for the payment of Rs. {{ $mailData['payment_amount'] }}.
        </p>
        <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">Be Wise. Stay Insured.</p>
    @endsection
