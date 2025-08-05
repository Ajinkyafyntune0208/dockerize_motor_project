@extends('admin_lte.layout.app', ['activePage' => 'kafka-logs', 'titlePage' => __('Kafka Logs')])
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul class="list">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<div class="card card-primary">
    <div class="card-body">
        <form action="" method="GET" name="serverErrorForm">
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label class="required">Enquiry Id</label>
                        <input id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" class="form-control" placeholder="Enquiry Id" required>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (!empty($logs))
<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table id='kafka-logs-table' class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Stage</th>
                        <th scope="col">Request</th>
                        <th scope="col">Source</th>
                        <th scope="col">Created At</th>

                    </tr>
                </thead>

                <tbody>
                    @foreach ($logs as $log)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td>{{ $log->stage }}</td>
                        <td>
                            <a class="btn btn-primary" title='View Request' href='{{ url('admin/kafka-logs', $log['encryptId']) }}'>
                                <i class="fa fa-eye"></i></a>
                        </td>
                        <td>{{ $log->source }}</td>
                        <td>{{ date('d-M-Y h:i:s A', strtotime($log->created_on)) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<p style="font-size: 1rem;" class="card p-3 text-danger">
    No Records or search for records
</p>
@endif
@endsection('content')

@section('scripts')
<script>
    $(function () {
    $("#kafka-logs-table").DataTable({
      "responsive": true, "lengthChange": true, "autoWidth": false,
      scrollX: true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:nth-child(n+3)'
                }]
    }).buttons().container().appendTo('#kafka-logs-table_wrapper .col-md-6:eq(0)');
  });
</script>
@endsection('scripts')