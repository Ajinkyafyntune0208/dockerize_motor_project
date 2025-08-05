@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
<div style="margin-bottom:20px">
    <p style="font-size: 0.9rem;">Thank you for trusting <a
            href="{{ config('BROKER_WEBSITE') }}">{{ config('BROKER_WEBSITE') }}</a> for your motor protection. You are
        just
        a few steps away from securing your {{ $mailData['product_code'] ?? '' }} with the best possible insurance plan.
        Please finish your policy proposal form by clicking on the button below:</p>
</div>

<div style="marging-top:10px;">
    <a href="{{ $mailData['link'] }}"><button
            style="font-weight:bold;background:#ed1c24;border-radius: 4px;padding:15px;color: #fff;border: none;">Complete
            your Policy Proposal </button></a>
</div>
@endsection
