<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry ID: {{ $log->enquiry_id ?? '' }}</title>
    <style>
.btn {
    background-color: #007bff; /* Green */
    border-color: #007bff; color: white; padding: .375rem .75rem;
    text-align: center; text-decoration: none;
    display: inline-block; font-size: 1rem;
    position: absolute; right: 20px; top: 20px;
}

.btn2 {
    background-color: #007bff; /* Green */
    border-color: #007bff; color: white; padding: .375rem .75rem;
    text-align: center; text-decoration: none;
    display: inline-block; font-size: 1rem;
    position: absolute; right: 120px; top: 20px;
}
</style>
</head>
<body>
    @can('log.request_response_tryit_option')
    <a target="_blank" href="{{ route('api.logs.response', ['type' => $log->transaction_type, 'id' => $log->id, 'view' => "Show-Request", 'enc' => enquiryIdEncryption($log->id), 'with_headers' => 1])}}"
        class="btn btn-sm btn-success">
        Try It!
    </a>
    @endcan

    <a target="_blank" href="{{ route('api.logs.view-download', ['type' => $log->transaction_type, 'id' => $log->id, 'view' => "download",'enc' => enquiryIdEncryption($log->id), 'with_headers' => 1]) }}"
        class="btn2 btn-sm btn-success">
        Download
    </a>
    <p><b>Trace Id :</b> {{ isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : ''}}</p>
    <p><b>Section :</b> {{ $log->section ?? '' }}</p>

    <p><b>Method Name :</b> {{ $log->method_name ?? '' }}</p>

    <p><b>Company :</b> {{ $log->company ?? '' }}</p>

    @php
    $quote_details = json_decode($log->vehicle_details, true)['quote_details'] ?? '';
    @endphp

    <p><b>Vehicle Details :</b></p>
    <ul>
      <li><b>Version ID. :</b> {{ $quote_details['version_id'] ?? '' }} </li>
      <li><b>Registration No. :</b> {{ $log->user_proposal_details->vehicale_registration_number ?? $quote_details['vehicle_registration_no'] ?? '' }} </li>
      <li><b>Registration Date. :</b> {{ $quote_details['vehicle_register_date'] ?? '' }} </li>
      <li><b>Fuel Type. :</b> {{ $quote_details['fuel_type'] ?? '' }} </li>
      <li><b>Make And Model. :</b> {{ ($quote_details['manfacture_name']  ?? '') . '  ' . ($quote_details['model_name'] ?? '') . '  ' . ($quote_details['version_name'] ?? '') }}</li>
    </ul>
    <p>
        <b>Response Time : </b> {{$log->response_time}}
    </p>
    <p>
        <b>Created At :</b> {{ isset($log->created_at) && !empty($log->created_at) ? date('d-M-Y h:i:s A', strtotime($log->created_at)) : ''}}
    </p>
    <p>
        <b>Request URL : </b>  {{ $log->endpoint_url ?? '' }}
    </p>
    <p>
        <b>Request Method : </b> {{ $log->method ?? '' }}
    </p>
    <p>
        <b>Headers : </b><br> {{ $log->headers ?? '' }}
    </p>
    <p>
        <b>Request : </b><br> {{ $log->request ?? '' }}
    </p>
    <p>
        <b>Response : </b><br> {{ $log->response ?? '' }}
    </p>

</body>
</html>
