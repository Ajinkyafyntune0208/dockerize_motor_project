@extends('admin_lte.layout.app', ['activePage' => 'renewal-data-logs', 'titlePage' => __('Renewal Data API Logs')])
@section('content')
<div class="card card-primary">
    <div class="card-body">
        <form action="" method="GET" name="serverErrorForm">
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label>Trace Id / RC Number<span class="text-danger">*</span></label>
                        <input id="userInput" name="TraceRcNumber" type="text" value="{{ old('userInput', request()->TraceRcNumber ?? null) }}" class="form-control" required placeholder="Trace Id / RC Number">
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
            Show
            <select name="paginateDropdown" style="width:50px;opacity:0.7;border-radius:3px;margin-right:5px;margin-left:5px">
                @foreach ($dropDownValues as $item)
                <option value="{{$item}}" {{request('paginate') && request('paginate') == $item ? 'selected' : '' }}>{{$item}}</option>
                @endforeach
            </select>
            entries
            <div class="table-responsive">
                <table id='trace-enquiry-table' class="table table-striped">
                    <thead>
                        <tr>
                            <th class="text-right" scope="col">View Request Response</th>
                            <th scope="col">#</th>
                            <th scope="col">Trace ID</th>
                            <th scope="col">RC Number</th>
                            <th scope="col">Policy Number</th>
                            <th scope="col">Created At</th>
                            <th scope="col">Updated At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($data as $d)
                        <tr>
                            <td class="text-right">
                                <div class="btn-group" role="group">
                                    @can("renewal_data_api_logs.show")
                                    <a class="btn btn-sm btn-primary" href="{{ route('admin.renewal-data-logs.show', [ 'renewal_data_log' => $d->id ] ) }}" target="_blank">
                                        <i class="fa fa-eye"></i>
                                    </a>

                                    <a class="btn btn-sm btn-success" href="{{ route('admin.renewal-data-logs.show', ['renewal_data_log' => $d->id, 'view' => "download"])}}"
                                        target="_blank">
                                        <i class="fa fa-arrow-circle-down"></i>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $d->traceId}}</td>
                            <td>{{ $d->registration_no }}</td>
                            <td>{{ $d->policy_number }}</td>
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
