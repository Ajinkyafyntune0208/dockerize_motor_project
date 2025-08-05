@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size:0.9rem;">Thank You!</p>
    <p style="margin-top: 1rem;margin-bottom: 1.5rem;font-size: 0.9rem;">Your payment has been
        successfully received. You can download your Policy document from the below attachment. You can also save and
        print these documents for future reference.</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Policy Number:
        {{ $mailData['policy_number'] }}</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Proposal Number:
        {{ $mailData['proposal_number'] }}</p>
@endsection
