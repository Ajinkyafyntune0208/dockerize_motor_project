@extends('admin_lte.layout.app', ['activePage' => 'pa_insurance_masters', 'titlePage' => __('PA insurance masters')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.pa-insurance-masters.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="form-group w-100">
                    <label for="excelFile">Choose excel file</label>
                    <input type="file" name="excelfile">
                    @error('excelfile')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-primary w-100" style="margin-top:16%">
                </div>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <table class="table table-striped" id="PA_reports">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Partyid</th>
                    <th>Created_at</th>
                    <th>Updated_at</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($pa_insurance_masters))
                @foreach($pa_insurance_masters as $key => $value)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $value->name }}</td>
                    <td>{{ $value->value }}</td>
                    <td>{{ $value->partyid }}</td>
                    <td>{{ $value->created_at }}</td>
                    <td>{{ $value->updated_at }}</td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

@endsection
@section('scripts')
<script>
    $("#PA_reports").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": true,
        "buttons": ["copy", "csv", "excel", "pdf", "print", {
            extend: 'colvis'
        }]
    }).buttons().container().appendTo('#PA_reports_wrapper .col-md-6:eq(0)');
</script>
@endsection