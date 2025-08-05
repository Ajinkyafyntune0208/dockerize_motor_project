<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RC No: {{ $report->registration_number ?? $report->policy_number ?? '' }}</title>
    <style>
.btn {
    background-color: #007bff; /* Green */
    border-color: #007bff; color: white; padding: .375rem .75rem;
    text-align: center; text-decoration: none;
    display: inline-block; font-size: 1rem;
    position: absolute; right: 20px; top: 20px;
} 
.table {
  width: 100%;
  max-width: 100%;
  margin-bottom: 1rem;
  background-color: transparent;
  border-collapse: collapse;
}

.table th,
.table td {
  padding: 0.75rem;
  vertical-align: top;
  border-top: 1px solid #dee2e6;
}

.table thead th {
  vertical-align: bottom;
  border-bottom: 2px solid #dee2e6;
}

.table tbody + tbody {
  border-top: 2px solid #dee2e6;
}

.table-bordered {
  border: 1px solid #dee2e6;
}

.table-bordered th,
.table-bordered td {
  border: 1px solid #dee2e6;
}
.text-center {
    text-align: center
}
</style>
    
</head>
<body>
    <a target="_blank" href="{{ route('admin.renewal-data-migration.download', ['id' => $report->id]) }}" class="btn btn-sm btn-success">Download</a>
    <p><b>Enquiry ID : </b> {{ !empty($report->user_product_journey_id) ? customEncrypt($report->user_product_journey_id) : '' }}</p>
    <p><b>RC Number : </b> {{ $report->registration_number ?? '' }}</p>
    <p><b>Policy Number : </b> {{ $report->policy_number ?? '' }}</p>
    <p><b>Status : </b> {{ $report->status ?? '' }}</p>
    <p><b>Action : </b> {{ $report->action ?? '' }}</p>
    @if (empty($report->migration_attempt_logs))
    <p><b>Attempts : </b>{{ $report->attempts ?? '' }}</p>
    @endif
    <p><b>Created At : </b> {{ $report->created_at ?? '' }}</p>
    <p><b>Ic Logs : <a href="{{ $url }}" target="_blank">Click here</a></p>
    <p><b>Policy Data : </b><br> {{  $report->request }}</p>

    @if (count($report->updation_log ?? []) > 0)
    @php
       $old_data = [];
        $new_data = [];
        foreach($report->updation_log as $log) {
          $old_data[$log->type][] = $log->old_data;
          $new_data[$log->type][] = $log->new_data;
        }
    @endphp
    <p><b>Old Data : </b><br> {{ json_encode($old_data) }}</p>
    <p><b>New Data : </b><br> {{ json_encode($new_data) }}</p>
    @endif
    @if (count($report->migration_attempt_logs) > 0)
    <h3> Renewal Attempt Logs: </h3>
    <table class="table table-bordered">
        <thead>
            <th>S. No</th>
            <th>Attempt</th>
            <th>Type</th>
            <th>Status</th>
            <th>Created At</th>
        </thead>
        <tbody>
          @foreach ($report->migration_attempt_logs as $attempt)
          <tr>
              <td class="text-center">{{$loop->iteration}}</td>
              <td class="text-center">
                  {{$attempt->attempt}}
              </td>
              <td class="text-center">{{$attempt->type}}</td>
              <td class="text-center">{{$attempt->status}}
                  @if (!empty($attempt->extras))
                      <br>
                      <small>(Reason : {{json_decode($attempt->extras, true)['reason'] ?? ''}})</small>
                  @endif
                  
              </td>
              <td class="text-center">{{$attempt->created_at}}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if (!empty($report->wealth_maker_api_log ?? null))
    <h3> Wealth Maker Api Logs: </h3>
    <p><b>Status : </b> {{ $report->wealth_maker_api_log->status ?? '' }}</p>
    <p><b>Created At : </b> {{ $report->wealth_maker_api_log->created_at ?? '' }}</p>
    <p><b>Request : </b> <br>{{ stripslashes($report->wealth_maker_api_log->request) }}</p>
    <p><b>Response : </b> <br>{{ stripslashes($report->wealth_maker_api_log->response) }}</p>
    @endif
</body>
</html>