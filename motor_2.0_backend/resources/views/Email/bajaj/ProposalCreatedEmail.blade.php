@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <div>
        <p style="margin-top: 1rem;margin-bottom:1.5rem;font-size: 0.9rem;;">Please <a href="{{ $mailData['link'] }}">Click
                Here</a> to pay the premium of your vehicle
            policy <b>{{ $mailData['vehicale_registration_number'] }}</b>. Proposal No. <b>{{ $mailData['proposal_no'] }}</b>. Your Total
            Payable Amount is <b>Rs. {{ $mailData['final_amount'] }}</b></p>

        <p style="margin-top: 1rem;margin-bottom:1.5rem;font-size: 0.9rem;;">Important: This link will expire at
            <b>{{ date('d F Y') . ' 23.59.' }}</b></p>

    </div>
@endsection