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
</style>
</head>


<body>
    <a target="_blank" href="{{ url('admin/ckyc-wrapper-logs-download/'.$log->id)}}"  
        class="btn btn-sm btn-success">
        Download
    </a>
    <p><b>Trace Id :</b> {{ isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : ''}}</p>

    <p><b>Mode :</b> {{ $log->mode ?? '' }}</p>

    <p><b>Company :</b> {{ $log->company_alias ?? '' }}</p>
    <p>
        <b>Failure Message: </b> {{$log->failure_message}}
    </p>
    <p>
        <b>Response Time : </b> {{$log->response_time}}
    </p>
    <p>
        <b>Request Start At :</b> {{ isset($log->start_time) && !empty($log->start_time) ? date('d-M-Y h:i:s A', strtotime($log->start_time)) : ''}}
    </p>
    <p>
        <b>Request End At :</b> {{ isset($log->end_time) && !empty($log->end_time) ? date('d-M-Y h:i:s A', strtotime($log->end_time)) : ''}}
    </p>
    <p>
        <b>Request URL : </b>  {{ $log->endpoint_url ?? '' }}
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