<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry ID: {{ $log->traceId ?? '' }}</title>
    <style>
.btn {
    background-color: #007bff; /* Green */
    border-color: #007bff; color: white; padding: .375rem .75rem;
    text-align: center; text-decoration: none;
    display: inline-block; font-size: 1rem;
    position: absolute; right: 20px; top: 20px;
} 
</style>
</head>
<body>
    <a target="_blank" href="{{ route('admin.renewal-data-logs.show', ['renewal_data_log' => $log->id, 'view' => "download"])}}"
        class="btn btn-sm btn-success">
        Download
    </a>
    <p><b>RC Number :</b> {{ $log->registration_no ?? '' }}</p>
    <p><b>Trace ID :</b> {{ $log->traceId ?? '' }}</p>
    <p>
        <b>Endpoint URL : </b>  {{ $log->url ?? '' }}
    </p>
    <p>
        <b>Request : </b><br> {{ $log->api_request ?? '' }}
    </p>
    <p>
        <b>Response : </b><br> {{ $log->api_response ?? '' }}
    </p>
    
</body>
</html>