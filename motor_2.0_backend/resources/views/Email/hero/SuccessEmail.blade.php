@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size: 0.9rem;">Policy booking for your {{ $mailData['product_code'] ?? '' }} was successful. Thank
        you for trusting <a href="{{ config('BROKER_WEBSITE') }}">{{ config('BROKER_WEBSITE') }}</a> for your motor
        protection. Please find
        attached the policy copy for your perusal.</p>
@endsection