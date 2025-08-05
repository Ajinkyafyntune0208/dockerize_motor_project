<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RC No: {{ $rc_report->request ?? '' }}</title>
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
    @php
        if(!empty(request()->enc)) {
            $link = route('api.vahan.view-download', ['id' => $rc_report->id, 'enc' => request()->enc, 'view'=> 'download']);
        } else {
            $link = route('admin.rc-report-download', ['id' => $rc_report->id]);
        }
    @endphp
    <a target="_blank" href="{{ $link }}" class="btn btn-sm btn-success">Download</a>
    <p>&nbsp;<b>RC Number : </b>&nbsp; {{ $rc_report->request ?? '' }}</p>
    
    <p>&nbsp;<b>Transaction Type :</b>&nbsp; {{ $rc_report->transaction_type ?? '' }}</p>

    <p>&nbsp;<b>Request URL : </b> &nbsp; {{ $rc_report->endpoint_url ?? '' }}</p>

    <p>&nbsp;<b>Request : </b> &nbsp;{{ $rc_report->request ?? '' }}</p>

    <p>&nbsp;<b>Response Time : </b> &nbsp;{{ $rc_report->response_time ?? '' }}</p>

    <p>&nbsp;<b>Created At : </b> &nbsp; {{ $rc_report->created_at ?? '' }}</p>

    <p>&nbsp;<b>Response : </b><br>
        @php
            $response = json_decode(($rc_report->response ?? ''), true);
            if(is_array($response) && !empty($response)) {
                $response = json_encode($response, JSON_PRETTY_PRINT);
            } else {
                $response = ($rc_report->response ?? '');
            }
        @endphp
        <pre> {{ $response }} </pre></p>
    
</body>
</html>