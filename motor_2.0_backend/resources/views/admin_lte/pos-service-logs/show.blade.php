<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <style>
        :root {
            --primary: #1f3bb3;
        }

        .btn-cus {
            position: absolute;
            right: 20px;
            top: 20px;
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

        .btn-cus:hover {
            background-color: var(--primary);
            color: white;
        }
    </style>
</head>

<body>
    <p><b>Agent ID:</b> {{ $log->agent_id ?? '' }}</p>
    <p><b>Company:</b> {{ $log->company ?? '' }}</p>
    <p><b>Section:</b> {{ $log->section ?? '' }}</p>
    <p><b>Transaction Type:</b> {{ $log->transaction_type ?? '' }}</p>
    <p><b>Method Name:</b> {{ $log->method_name ?? '' }}</p>
    <p><b>Product:</b> {{ $log->product ?? '' }}</p>
    <p><b>HTTP Method:</b> {{ strtoupper($log->method ?? '') }}</p>
    <p><b>Endpoint URL:</b> {{ $log->endpoint_url ?? '' }}</p>
    <p><b>IP Address:</b> {{ $log->ip_address ?? '' }}</p>
    <p><b>Status:</b> {{ $log->status ?? 'N/A' }}</p>
    <p><b>Server Status Code:</b> {{ $log->status_code ?? 'N/A' }}</p>
    <p><b>Start Time:</b> {{ $log->start_time ?? '' }}</p>
    <p><b>End Time:</b> {{ $log->end_time ?? '' }}</p>
    <p><b>Response Time:</b> {{ $log->response_time ?? '' }} seconds</p>

    <hr>

    <pre class="text-wrap"><b>Request Headers:</b>
{{ json_encode(json_decode($log->headers, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
</pre>
    <hr>

    <pre class="text-wrap"><b>Request Body:</b>
{{ $log->request }}
</pre>

    <hr>

    <pre class="text-wrap"><b>Response Body:</b>
{{ $log->response }}
</pre>

    @if($log->message)
    <hr>
    <p><b>Message:</b> {{ $log->message }}</p>
    @endif
    @if($log->responsible)
    <p><b>Responsible:</b> {{ $log->responsible }}</p>
    @endif
</body>

</html>