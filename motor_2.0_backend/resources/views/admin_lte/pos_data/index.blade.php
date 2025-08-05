@extends('admin_lte.layout.app', ['activePage' => 'pos-data', 'titlePage' => __('POS Data')]) 
@section('content')
<div class="card">
    <div class="card-body">
        <form enctype="multipart/form-data" method="GET">
            <div class="form-group">
                <label>Select Company Name :</label>
                <select required name="table_name" id="table_data"
                    data-actions-box="true" class="select2 w-50 form-control" data-live-search="true">
                    <option value="">Nothing selected</option>
                    @foreach ($company as $company_ic)
                        <option
                            {{ request()->table_name == $company_ic->company_alias ? 'selected' : '' }}
                            value="{{ $company_ic->company_alias }}">
                            {{ $company_ic->company_alias }}
                        </option>
                    @endforeach
                </select>
                @error('table_name')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <button class="btn btn-primary" type="submit" ><i class="fa fa-search"></i> Search</button>
            </div>
        </form>
    </div>
</div>
@if (!empty($table_data))
<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                @if (!empty($table_data[0]))
                    <tr>
                        <th>view</th>
                        @foreach ($table_data[0] as $key => $table)
                            <th>{{ $key }}</th>
                        @endforeach
                    </tr>
                @else
                    <p>No data available in table</p>
                @endif
            </thead>
            <tbody>
                @foreach ($table_data as $key => $table)
                    <tr>
                        <td> <a href="{{ url('admin_lte/pos-data/' . $table->ic_mapping_id . '?table=' . request()->table_name) }}"
                                class="btn btn-primary center float-end btn-sm" target="_blank"><i
                                    class="fa fa-eye"></i></a></td>
                        @foreach ($table as $k => $v)
                            <td>{{ $v }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
@section('scripts')
    <script>
        let data = @json($table_data[0] ?? '');
        if(data){
            $(function () {
                $("#data-table").DataTable({
                    "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
                }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
            });
        }
    </script>
@endsection
