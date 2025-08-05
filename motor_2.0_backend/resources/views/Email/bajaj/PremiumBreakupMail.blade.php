@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size: 0.9rem;">Thank you for choosing your policy on <b><a
                href="{{ config('BROKER_WEBSITE') }}">{{ config('BROKER_WEBSITE') }}</a>.</b></p>
    <p style="font-size: 0.9rem;">Following are the quote details and a detailed quote file is attached with the email.</p>


    <div style="margin-bottom:20px;">
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
            Registration Date :
            {{ $mailData['reg_date'] }}
        </p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
            Previous Expiry Date:
            {{ $mailData['previos_policy_expiry_date'] }}
        </p>
        <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
            Vehicle model:
            {{ $mailData['vehicle_model'] }}
        </p>
        @if (isset($mailData['regNumber']) && $mailData['regNumber'] != '')
            <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
                Vehicle Registration
                No:
                {{ $mailData['regNumber'] }}
            </p>
        @else
            <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
                RTO Code:
                {{ $mailData['rto'] }}
            </p>
        @endif
    </div>

    <div style="marging-top:10px;">
        <a href="{{ $mailData['premium_data']['btn_link'] }}"><button
                style="font-weight:bold;background:#ed1c24;border-radius: 4px;padding:15px;color: #fff;border: none;">BUY NOW</button></a>
    </div>
@endsection
