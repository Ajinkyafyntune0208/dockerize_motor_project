@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size: 0.9rem;">You are just few steps away from
        securing your Vehicle</p>
    <p style="font-size: 0.9rem;">Continue your proposal on PINC Tree by
        clicking <a href="{{ $mailData['link'] }}">Link.</a>
    </p>
@endsection
