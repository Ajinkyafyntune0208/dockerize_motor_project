@extends('admin_lte.layout.app', ['activePage' => 'configuration', 'titlePage' => __('System configuration')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="" method="GET" name="ConfigSettingsForm" onsubmit="return validateForm()">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <input id="config_search" name="config_search" type="text" value="{{ old('config_search', request()->config_search ?? null) }}" class="form-control" placeholder="Search by Label / Key / Value" required>
                    </div>
                </div>

                <input type="hidden" name="paginate" value="30">
                <div class="col-sm-6">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Search</button>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        @can('system_configuration.create')
                            <a class="btn btn-primary" href="{{ route('admin.configuration.create') }}">+ Add Config</i></a>
                        @endcan
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover system-configuration">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>#</th>
                    <th>Label</th>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Environment</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($configs as $key => $value)
                <tr>
                    <td>

                    <!-- <div class="info-box"> -->
                        <div class="btn-group btn-group-toggle">
                            @can('system_configuration.edit')
                            <a class="btn btn-info mr-1" href="{{ route('admin.configuration.edit', $value->id) }}"><i class="far fa-edit"></i></a>
                            @endcan
                            @can('system_configuration.delete')
                            <form action="{{ auth()->user()->can('configuration.delete') ? route('admin.configuration.destroy', $value) : '' }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    <!-- </div> -->
                    </td>
                    <td>{{++$key}}</td>
                    <td><i class="text-info fa fa-copy ml-2" role="button"></i> {{$value->label}}</td>
                    <td><i class="text-info fa fa-copy ml-2" role="button"></i> {{$value->key}}</td>
                    <td><i class="text-info fa fa-copy ml-2" role="button"></i> {{$value->value}}</td>
                    <td>{{$value->environment}}</td>
                    <td>{{$value->created_at}}</td>
                    <td>{{$value->updated_at}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection('content')
@push('scripts_lte')
<script>
    $(function () {
        $("#data-table").DataTable({
            "responsive": false, "lengthChange": true, "autoWidth": true,
            scrollX: true,
            "buttons": ["copy", "csv", "excel",{
                extend: 'pdfHtml5',
                orientation : 'landscape',
                pageSize : 'A2',
            },
            "print",  {
                extend: 'colvis',
                columns: 'th:not(:nth-child(3))'
            },
        ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
    });

    $(document).ready(function()
    {
        $(document).on('click', '.fa.fa-copy', function() {
            var text = $(this).parent('td').text();
            const elem = document.createElement('textarea');
            elem.value = text.trim();
            document.body.appendChild(elem);
            elem.select();
            document.execCommand('copy');
            document.body.removeChild(elem);
            alert('Text copied...!')
        });
    });
</script>
@endpush('scripts_lte')
