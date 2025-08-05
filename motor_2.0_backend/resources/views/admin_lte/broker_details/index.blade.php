@extends('admin_lte.layout.app', ['activePage' => 'Broker', 'titlePage' => __('Broker Details')])
@section('content')
    <div class="card">
        <div class="card-header">
            @can('broker_details.create')
            <a href="{{ route('admin.broker.create') }}" class="btn btn-primary float-right">Add Broker</a>
            @endcan
        </div>
        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Sr. No.</th>
                        <th>Name</th>
                        <th>Frontend URL</th>
                        <th>Backend URL</th>
                        <th>Environment</th>
                        <th>Support Email</th>
                        <th>Support Tollfree</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($broker_details as $key => $value)
                        <tr>
                            <td>
                                <div class="btn-group btn-group-toggle">
                                    @can('broker_details.edit')
                                    <a class="btn btn-info mr-1" href="{{ route('admin.broker.edit', $value) }}"><i
                                            class="far fa-edit"></i></a>
                                            @endcan
                                    @can('broker_details.delete')
                                    <form action="{{ route('admin.broker.destroy', $value) }}" method="post"
                                        onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                        <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                            <td>{{ ++$key }}</td>
                            <td>{{ $value->name }}</td>
                            <td>
                                @if (@isset($value) && !empty($value->frontend_url))
                                    {{ $value->frontend_url }}
                                    <button class="btn" onclick="copyToClipboard('{{ $value->frontend_url }}')"><i
                                            class="fa fa-clipboard"></i></button>
                                @endif

                            </td>
                            <td>
                                @if (@isset($value) && !empty($value->backend_url))
                                    {{ $value->backend_url }}
                                    <button class="btn" onclick="copyToClipboard('{{ $value->backend_url }}')"><i
                                            class="fa fa-clipboard"></i></button>
                                @endif

                            </td>
                            <td>{{ $value->environment }}</td>
                            <td>{{ $value->support_email }}</td>
                            <td>{{ $value->support_tollfree }}</td>
                            <td>
                                @if (strtolower($value->status) == 'active')
                                    Active
                                @else
                                    Inactive
                                @endif

                            </td>


                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal -->
@endsection('content')
@section('scripts')
    <script>
        $(function() {
            $("#data-table").DataTable({
                "responsive": false,
                "lengthChange": true,
                "autoWidth": true,
                scrollX: true,
                "buttons": ["copy", "csv", "excel", {
                    extend: 'pdfHtml5',
                    orientation: 'landscape',
                    pageSize: 'A2',
                }, "print", {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                }, ]
            }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
        });



        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
@endsection('scripts')
