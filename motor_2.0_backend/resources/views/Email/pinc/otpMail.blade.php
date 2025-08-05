@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size: 0.9rem;">The OTP for doing payment on {{ config('app.name') }} for proposal
        {{ $mailData['proposal_number'] }}<br>
    </p>
    <h3 style="text-align: center;">{{ $mailData['otp'] }}</h3>
@endsection
