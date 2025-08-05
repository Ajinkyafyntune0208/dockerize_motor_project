@extends('admin_lte.layout.app', ['activePage' => 'kafka-sync', 'titlePage' => __('Kafka Sync')])
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
        <form action="" method="GET">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="{{ old('from_date', request()->from_date ?? '') }}" class="form-control">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="{{ old('to_date', request()->to_date ?? '') }}" class="form-control">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <input type="hidden" name="form_submit" value=true>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (!empty($logs))
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <h3 class="text-primary text-left mb-2 border-end">Fyntune Kafka Stats</h3>
                                    <ul>
                                        @foreach($paymentStatus as $Status)
                                        <li><b>{{ $Status->rb_status }} : </b> {{ $Status->total }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-primary text-left mb-2 border-start">RB Kafka Stats</h3>
                                    <ul>
                                        @if (in_array(gettype($logs),['array','object']))
                                            @foreach($logs as $key => $log)
                                                <li><b>{{ $key }} : </b> {{ $log }}</li>
                                            @endforeach
                                        @endif
                                    </ul>
                                </div>
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