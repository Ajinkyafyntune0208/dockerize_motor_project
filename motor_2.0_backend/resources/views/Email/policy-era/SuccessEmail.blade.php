@extends('Email.policy-era.layout')
@section('content')
    <p style="margin-top:1rem;font-size: 0.9rem;font-weight: bold;">Thank You!</p>
    <p style="margin-top:1.5rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">Your payment has
        been successfully received. You can download your Policy document from the below attachment. You can
        also save and print these documents for future reference.</p>
    <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Policy Number:
        {{ $mailData['policy_number'] }}</p>
    <p style="margin: 0;padding: 0; color: #075672;font-size: 0.9rem;font-weight: bold;">Proposal Number:
        {{ $mailData['proposal_number'] }}</p>
@endsection
