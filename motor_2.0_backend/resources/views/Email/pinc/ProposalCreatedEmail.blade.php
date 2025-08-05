@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">Please <a
            href="{{ $mailData['link'] }}">Click here </a> to pay the premium for your vehicle policy
        {{ $mailData['vehicale_registration_number'] }}. Proposal No. {{ $mailData['proposal_no'] }}.</p>
    <p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">Your Total
        Payable Amount is Rs. {{ $mailData['final_amount'] }}.</p>
    <p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">Important: This
        link will expire at {{ now()->format('d-m-Y') }} 23:59.</p>
@endsection