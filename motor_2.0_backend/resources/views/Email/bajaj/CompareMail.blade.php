@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p  align="justify" style="font-size: 0.9rem;">Thank you for trusting <a href="{{ config("BROKER_WEBSITE")}}">{{ config("BROKER_WEBSITE")}}</a> for your motor protection. Please find
        attached a comparison of {{ $mailData['product_code'] ?? '' }} Insurance - {{ $mailData['productName'] ?? ''}} based on your requirements:</p>

    <div style="margin-bottom:20px;">
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Registration Date :
            {{ $mailData['reg_date'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Previous Expiry Date:
            {{ $mailData['previos_policy_expiry_date'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Vehicle model:
            {{ $mailData['vehicle_model'] }}</p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Rto: {{ $mailData['rto'] }}</p>
    </div>

    <div style="marging-top:10px;">
        <a href="{{ $mailData['link'] }}"><button
                style="font-weight:bold;background:#ed1c24;border-radius: 4px;padding:15px;color: #fff;border: none;">BUY NOW</button></a>
    </div>
@endsection
