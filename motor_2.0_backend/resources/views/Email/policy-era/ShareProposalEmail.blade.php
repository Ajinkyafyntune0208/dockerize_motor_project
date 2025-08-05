@extends('Email.policy-era.layout')
@section('content')
    <p style="margin-top: 2.5rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">You are just few
        steps away from securing your {{ $mailData['product_name'] }}.</p>
    <p style="font-size: 0.9rem;">Continue your proposal on {{ config('app.name') }} by
        clicking <a href="{{ $mailData['link'] }}">Link.</a></p>
@endsection
