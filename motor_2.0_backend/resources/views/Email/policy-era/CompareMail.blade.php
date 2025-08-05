@extends('Email.policy-era.layout')
@section('content')
    <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">Please find
        attached comparision of {{ $mailData['productName'] }} Insurance based on following details.</p>

        @if (!empty($mailData['reg_date']))
            <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Registration Date :
                {{ $mailData['reg_date'] }}</p>
        @endif
        @if (!empty($mailData['previos_policy_expiry_date']))
            <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Previous Expiry
                Date: {{ $mailData['previos_policy_expiry_date'] }}</p>
        @endif
        @if (!empty($mailData['vehicle_model']))
            <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Vehicle model:
                {{ $mailData['vehicle_model'] }}</p>
        @endif
        @if (!empty($mailData['rto']))
            <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Rto:
                {{ $mailData['rto'] }}</p>
        @endif

@endsection
