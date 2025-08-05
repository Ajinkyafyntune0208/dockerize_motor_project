@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <div style="padding: 0px 10px;">
        @if (isset($mailData['isOtpRequired']) &&  $mailData['isOtpRequired'] == "Y")
            <p style="font-size: 0.9rem;">
                Your OTP for your journey on {{ config('app.name') }} <br>
            <h3 style="text-align: center;">{{ $mailData['otp'] }}</h3>
            </p>
        @else
            <p style="font-size: 0.9rem;">
                The OTP for doing payment {{ config('app.name') }} <br>
            <h3 style="text-align: center;">{{ $mailData['otp'] }}</h3>
            </p>
        @endif
    </div>
@endsection
