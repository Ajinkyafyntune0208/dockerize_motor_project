@extends('Email.policy-era.layout')
@section('content')
    <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">Please find
        attached premium breakup of your {{ $mailData['productName'] }} insurance based on the following
        details.</p>

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
    @if (isset($mailData['regNumber']) && $mailData['regNumber'] != '')
        <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Vehicle
            Registration No: {{ $mailData['regNumber'] }}</p>
    @else
        <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">RTO Code:
            {{ $mailData['rto'] }}</p>
    @endif
@endsection
