<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebService Request Response</title>
</head>

<body>
    <table style="text-align: center; width: 100%">
        <tr>
            <th>Enquiry Id</th>
            <th>Company</th>
            <th>Section</th>
            <th>Method Name</th>
            <th>Manufactur Name</th>
            <th>Model</th>
            <th>Fuel</th>
            <th>Version ID</th>
            <th>Varient</th>
            <th>Product</th>
            <th>Endpoint Url</th>
            <th>Method</th>
            <th>View Request</th>
            <th>View Response</th>
            <th>Transaction Type</th>
            <th>IP Address</th>
            <th>Created At</th>
        </tr>
        @foreach($logs as $key => $log)
        <tr>
            <td>{{ $log->enquiry_id }}</td>
            <td>{{ $log->company }}</td>
            <td>{{ $log->section }}</td>
            <td>{{ $log->method_name }}</td>
            <td>{{ $log->vehicle_details->quote_details['manfacture_name'] ?? null }}</td>
            <td>{{ $log->vehicle_details->quote_details['model_name'] ?? null }}</td>
            <td>{{ $log->vehicle_details->quote_details['fuel_type'] ?? null }}</td>
            <td>{{ $log->vehicle_details->quote_details['version_id'] ?? null }}</td>
            <td>{{ $log->vehicle_details->quote_details['version_name'] ?? null }}</td>
            <td>{{ $log->product }}</td>
            <td>{{ $log->endpoint_url }}</td>
            <td>{{ $log->method }}</td>
            @if( $log->endpoint_url == 'Internal Service')
            <td>
                {{ $log->request }}
            </td>
            <td>
                {{  $log->response }}
            </td>
            @else
            <td colspan="2"><a href="{{ route('admin.log.show', $log) }}">View Request Response</a></td>
            @endif
            <td>{{ $log->transaction_type }}</td>
            <td>{{ $log->ip_address }}</td>
            <td>{{ \Carbon\Carbon::parse($log->start_time)->format('d-M-Y H:i:s A') }}</td>
        </tr>
        @endforeach
    </table>
</body>

</html>