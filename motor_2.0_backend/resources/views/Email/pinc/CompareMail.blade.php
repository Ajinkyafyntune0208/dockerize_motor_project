@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
    <p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">Please find attached comparison of
        Vehicle Insurance based on following details.
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
    <p style="margin: 0;padding: 0;font-size: 0.9rem;">Rto Code: {{ $mailData['rto'] }}</p>
@endsection
