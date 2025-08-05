@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')
<p style="margin-top: 1rem;margin-bottom: 1rem;font-size: 0.9rem;">
    Your Inspection request on PINC Tree with {{ $mailData['insurer'] }} for vehicle reg no.
    {{ $mailData['registration_number'] }}
    has been raised with ID/Reference ID {{ $mailData['reference_id'] }} on {{ $mailData['time_stamp'] }}.
</p>
@endsection
