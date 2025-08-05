<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry ID: {{ customEncrypt($log->enquiry_id) ?? '' }}</title>
    <style> 
    :root{
    --primary: #1f3bb3;
    }
    .btn-cus{
        position: absolute; right: 20px; top: 20px;
        height: 40px;
        width: 100px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
</style>
</head>
<body>
    <a class="btn btn-cus" 
        href="{{ route('admin.datapush_log_download', ['type' => 'datapushlog','id'=>$log->id ] )}}"  target="_blank" >
        Download
    </a>
    <p><b>Enquiry ID:</b> {{ isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : ''}}</p>
    <pre><b>Request Headers:</b> {{ json_encode($log->request_headers ?? '',JSON_PRETTY_PRINT)}}</pre>
    <p><b>Url:</b> {{ $log->url ?? '' }}</p>
    <hr>
    <p><b>Status:</b> {{ $log->status ?? '' }}</p>
    <p><b>Server Status Code:</b> {{ $log->status_code ?? '' }}</p>
    <hr>
    <pre class=" text-wrap"><b>Request:</b> {{ json_encode($log->request ?? '',JSON_PRETTY_PRINT) }}</pre>
    <hr>
    <pre class="text-wrap"><b>Response:</b> {{ json_encode($log->response ?? '',JSON_PRETTY_PRINT) }}</pre>

</body>
</html>
