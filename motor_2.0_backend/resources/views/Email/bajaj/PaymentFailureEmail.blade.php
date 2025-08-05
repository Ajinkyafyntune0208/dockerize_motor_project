@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p> We regret to inform that policy purchase of your {{ $mailData['product_code'] ?? '' }} was unsuccessful. please
        <a href="{{ $mailData['link'] ?? '' }}">Click Here</a> to re-initiate the journey</p>
@endsection
