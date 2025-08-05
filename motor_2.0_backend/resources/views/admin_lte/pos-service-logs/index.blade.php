@extends('admin_lte.layout.app', ['activePage' => 'pos-service-logs', 'titlePage' => __('Pos Services Logs')])
@section('content')
<div class="card card-primary">
    <!-- form start -->
    <div class="card-body">
        <form action="{{route('admin.pos-service-logs.index')}}">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Agent id <span class="text-danger"> *</span></label>
                        <input class="form-control" id="Agentid" name="Agentid" type="text" value="{{ old('Agentid', request()->Agentid ?? null) }}" placeholder="Agent id">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                </div>
            </div>
            <a target="_blank" href="{{ route('admin.pos-service-logs.create') }}"
                class="btn2 btn-sm btn-success float-right">
                Download
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table class="table-striped table" id="result">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">View</th>
                        <th scope="col">Agent id</th>
                        <th scope="col">Company</th>
                        <th scope="col">Section</th>
                        <th scope="col">Product</th>
                        <th scope="col">transaction_type</th>
                        <th scope="col">Created At</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($logs as $log)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="{{route('admin.pos-service-logs.show', $log['id'])}}" target="_blank"><i class="fa fa-eye"></i></a>
                        </td>
                        <td>{{ $log['agent_id'] }}</td>
                        <td>{{ $log['company'] }}</td>
                        <td>{{ $log['section'] }}</td>
                        <td>{{ $log['product'] }}</td>
                        <td>{{ $log['transaction_type'] }}</td>
                        <td>{{ $log['created_at'] }}</td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection('content')

@section('scripts')
<script>
    $("#result").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": true,
        "pageLength": 1000,
        "buttons": ["copy", "csv", "excel", "pdf", "print", {
            extend: 'colvis'
        }]
    }).buttons().container().appendTo('#result_wrapper .col-md-6:eq(0)');
</script>
@endsection('scripts')