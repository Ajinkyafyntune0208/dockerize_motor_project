@extends('admin_lte.layout.app', ['activePage' => 'template', 'titlePage' => __('Template List')])

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="card-tools">
                @can('template_master.create')
                <a href="{{ route('admin.template.create') }}">
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Template
                    </button>
                </a>
                @endcan
            </div>
        </div>

        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Sr. No.</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $key => $value)
                        <tr>
                            <td>
                                <div class="btn-group btn-group-toggle">
                                    @can('template_master.edit')
                                    <a class="btn btn-info mr-1" href="{{ route('admin.template.edit', $value) }}"><i
                                            class="far fa-edit"></i></a>
                                     @endcan
                                     @can('template_master.delete')
                                    <form action="{{ route('admin.template.destroy', $value) }}" method="post"
                                        onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                        <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                            <td>{{ ++$key }}</td>
                            <td>{{ $value->title }}</td>
                            <td>{{ $value->communication_type }}</td>
                            <td>{{ $value->status }}</td>
                            <td>{{ $value->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function() {
            $("#data-table").DataTable({
                "responsive": false,
                "lengthChange": true,
                "autoWidth": false,
                "scrollX": true,
                "buttons": [
                    "copy",
                    "csv",
                    "excel",
                    {
                        extend: "pdf",
                        customize: function (doc) {
                            // Align all text in the table to center
                            doc.content[1].table.widths = ['10%', '10%', '30%', '20%', '20%', '10%'];
                            doc.styles.tableBodyEven.alignment = 'center';
                            doc.styles.tableBodyOdd.alignment = 'center';
                            doc.styles.tableHeader.alignment = 'center';
                            doc.styles.tableHeader.fontSize = 10;
                            doc.styles.tableBodyEven.fontSize = 9;
                            doc.styles.tableBodyOdd.fontSize = 9;
                        }
                    },
                    "print",
                    {
                        extend: 'colvis',
                        columns: 'th:not(:nth-child(3))'
                    }
                ]
            }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
        });
    </script>
@endsection
