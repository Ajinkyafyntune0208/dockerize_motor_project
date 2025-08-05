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
    {{-- <a target="_blank" href="{{ route('api.logs.view-download', ['type' => $log->transaction_type, 'id' => $log->id, 'view' => "download"]) . '?with_headers=1' }}"  
        class="btn btn-sm btn-success">
        Download
    </a> --}}
    <a target="_blank" href="{{ url('admin/vahan-service-logs/'. $log->id . '?action=download')}}" 
        class="btn btn-sm btn-success">
        Download
    </a>

    <p><b>Vehicle Registration Number :</b> {{ $log->vehicle_reg_no ?? '' }}</p>
    
    <p><b>Created At :</b> {{ $log->created_at ?? '' }}</p>
    
    <p><b>Request : </b><br><pre> {{ $log->request }} </pre></p>
    
    <p><b>Response : </b><br><pre> {{ $log->response }} </pre></p>
    
</body>
</html>