@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Users List</h3>
            <div class="card-tools">
                @can('user.create')
                <a href="{{ route('admin.user.create') }}">
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Sr.No</th>
                        <th>Action</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Authorization By User</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $key => $value)
                        @php
                            $userRole = $value->getRoleNames()->first();
                        @endphp
                        <tr>
                            <td>{{ ++$key }}</td>
                            <td>
                                <div class="btn-group btn-group-toggle">
                                    @can('user.edit')
                                    <a class="btn btn-info mr-1" href="{{ route('admin.user.edit',$value) }}"><i
                                            class="far fa-edit"></i></a>
                                    @endcan
                                    
                                    @can('user.delete')
                                    <form action="{{ route('admin.user.destroy', $value) }}" method="post"
                                        onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                        <button class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                    @endcan
                                    
                                    @if($value->otp_type == 'totp')
                                    <button onclick="confirmAction('{{ $value->email }}')" class="border btn btn-secondary ml-1" title="QR Code"><i class="fa fa-qrcode"></i></button>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $value->name }}</td>
                            <td>{{ $value->email }}</td>
                            <td>
                                @foreach ($roles as $role)
                                    {{ old('role', $value->getRoleNames()->first()) == $role->name ? $role->name : '' }}
                                @endforeach
                            </td>

                            <td>
                                @if (isset($value->authorization_by_user) && !empty($value->authorization_by_user))
                                    {{ $value->authorization_by_user }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $value->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection('content')
@section('scripts')
    <script>
        $(function() {
            $("#data-table").DataTable({
                "responsive": false,
                "lengthChange": true,
                "autoWidth": false,
                scrollX: true,
                "buttons": ["copy", "csv", "excel", "pdf", "print", {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(3))'
                }]
            }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
        });

        function confirmAction(email) {
        var result = confirm("Are you sure you want to proceed?");
        if (result) {
            $.ajax({
                url: '/admin/request_email',
                data: {
                    _token: '{{ csrf_token() }}',
                    requested_email: email
                },
                success: function(response) {
                    alert("Action completed successfully!");
                },
                error: function(xhr, status, error) {
                    alert("An error occurred: " + xhr.responseText);
                    console.error(xhr, status, error);
                }
            });
        } else {
            alert("User canceled!");
        }
    }
    </script>
@endsection('scripts')
