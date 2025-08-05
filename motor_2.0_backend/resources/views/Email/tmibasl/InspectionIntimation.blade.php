@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>

        <p style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;"> Your Inspection request with
            {{ $mailData['insurer'] }} for vehicle {{ $mailData['registration_number'] }} is raised with ID/Reference ID
            {{ $mailData['reference_id'] }} on {{ $mailData['time_stamp'] }}.
        </p>
    @endsection
