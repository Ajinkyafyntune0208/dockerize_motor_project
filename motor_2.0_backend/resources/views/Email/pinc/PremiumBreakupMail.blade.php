@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="font-size: 0.9rem;">Thank You!</p>
    <p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">Please find attached premium
        breakup of your {{ ucfirst(strtolower($mailData['product_code'])) ?? '' }} insurance based on the following details.
    </p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Registration Date :
        {{ $mailData['reg_date'] }}</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Previous Expiry Date:
        {{ $mailData['previos_policy_expiry_date'] }}</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Vehicle Manfacturer:
        {{ $mailData['quote_data']['quote_details']['manfacture_name'] ?? '' }}</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Vehicle Model:
        {{ $mailData['quote_data']['quote_details']['model_name'] ?? '' }}</p>
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Vehicle Variant:
        {{ $mailData['quote_data']['quote_details']['version_name'] ?? '' }}</p>
    @if (isset($mailData['regNumber']) && $mailData['regNumber'] != '')
        <p style="margin: 0;padding: 0;font-size: 0.9rem;">Vehicle Registration No:
            {{ $mailData['regNumber'] }}</p>
    @else
        <p style="margin: 0;padding: 0;font-size: 0.9rem;">RTO Code:
            {{ $mailData['rto'] }}</p>
    @endif
@endsection