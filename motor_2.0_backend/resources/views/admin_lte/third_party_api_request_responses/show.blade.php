<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID: {{ $tp->id ?? '' }}</title>
    <style>
        .btn {
            background-color: #007bff;
            /* Green */
            border-color: #007bff;
            color: white;
            padding: .375rem .75rem;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            position: absolute;
            right: 20px;
            top: 20px;
        }
    </style>
</head>

<body>
    <a target="_blank" href="{{ url('admin/third_party_api_request_responses/'. $tp->id . '?action=download')}}" 
        class="btn btn-sm btn-success">
        Download
    </a>


    <p><b>Name :</b> {{ $tp->name ?? '' }}</p>

    <p><b>Url :</b> {{ $tp->url ?? '' }}</p>

    <p><b>Request :</b> {{ json_encode($tp->request, JSON_PRETTY_PRINT) ?? '' }}</p>

    <p><b>Response :</b> {{ json_encode($tp->response, JSON_PRETTY_PRINT) ?? '' }}</p>

    <p><b>Headers :</b> {{ json_encode($tp->headers) ?? '' }}</p>

    <p><b>Response Headers :</b> {{ json_encode($tp->response_headers, JSON_PRETTY_PRINT) ?? '' }}</p>

    <p><b>Options :</b> {{ json_encode($tp->options, JSON_PRETTY_PRINT) ?? '' }}</p>

    <p><b>Response Time :</b> {{ json_encode($tp->response_time) ?? '' }}</p>

    <p><b>Http Status :</b> {{ json_encode($tp->http_status) ?? '' }}</p>

    <p><b>Created At :</b> {{ $tp->created_at ?? '' }}</p>
</body>

</html>