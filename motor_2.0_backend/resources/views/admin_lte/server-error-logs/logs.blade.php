@extends('admin_lte.layout.app', ['activePage' => 'server-error-logs', 'titlePage' => __('Server Error Logs')])
@section('content')
<div class="card card-primary">
    <!-- form start -->
    <div class="card-body">
        <form action="" method="GET" name="serverErrorForm">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>From Date <span class="text-danger"> *</span></label>
                        <input id="userInput" name="from" type="date" value="{{ old('userInput', request()->from ?? null) }}" class="form-control" required>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="to">To Date</label>
                        <input id="userInput" name="to" type="date" value="{{ old('userInput', request()->to ?? null) }}" class="form-control" required>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <input type="hidden" name="paginate" value="30">
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (count($data) > 0)
@php
$dropDownValues = [10,20,30,40,50];
@endphp
<div class="card">
        <div class="card-body">

            <div class="table-responsive">
                <table id='trace-enquiry-table' class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Source</th>
                            <th scope="col">Url</th>
                            <th scope="col">Request</th>
                            <th scope="col">Error</th>
                            <th scope="col">Created At</th>
                            <th scope="col">Updated At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($data as $d)
                        <tr>
                            <td scope="row">{{ $loop->iteration }}</td>
                            <td>{{ strtoupper($d->source) }}</td>
                            <td>{{ $d->url ? url($d->url)  : 'NA'}}</td>
                            <td>{{ $d->request}}</td>
                            <td>{{ $d->error }}</td>
                            <td>{{ Carbon\Carbon::parse($d->created_at)->format('d-M-Y H:i:s A') }}</td>
                            <td>{{ Carbon\Carbon::parse($d->updated_at)->format('d-M-Y H:i:s A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">{{$data->appends(request()->query())->links()}}</td>
                        </tr>
                    </tfoot>
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

@push('scripts')
<script>
    $(function() {
        $("#data-table").DataTable({
            "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
    });
</script>
@endpush