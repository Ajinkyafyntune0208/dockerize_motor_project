@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Users List')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Users List
                        @if(auth()->user()->can('user.create'))
                        <a href="{{ route('admin.user.create') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-plus"></i> Add User</a>
                        @endif
                    </h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-striped" id="policy_reports">
                            <thead>
                                <tr>
                                    <th scope="col">Sr. No.</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col" class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $key => $user)
                                @php
                                    $userRole = $user->getRoleNames()->first();
                                @endphp
                                @if (!(auth()->user()->email === 'motor@fyntune.com')  && $userRole === 'webservice')
                                    @continue
                                @endif
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @foreach($roles as $role)
                                            {{ old('role', $user->getRoleNames()->first()) == $role->name ? $role->name : '' }}
                                        @endforeach
                                    </td>
                                    <td>{{ $user->created_at }}</td>
                                    <td>
                                    <form id="deleteForm" action="{{ route('admin.user.destroy', $user) }}" method="post" style="display: none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                        <div class="btn-group">
                                            @if(auth()->user()->can('user.show'))
                                            <!-- <a href="{{ route('admin.user.show', $user) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                            @endif
                                            @if(auth()->user()->can('user.edit'))
                                                @if (!($user->email==='motor@fyntune.com'))
                                                    <a href="{{ route('admin.user.edit', $user) }}" class="btn btn-sm btn-outline-success show-template"><i class="fa fa-edit"></i></a>
                                                @endif
                                            @endif
                                            @if(auth()->user()->can('user.delete'))
                                                @if (!($user->email==='motor@fyntune.com'))
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="confirmDelete()"><i class="fa fa-trash"></i></button>
                                                @endif
                                            @endif
                                        
                                            @if($user->otp_type == 'totp')
                                            <button onclick="confirmAction('{{ $user->email }}')" class="btn btn-sm btn-outline-success show-template"><i class="fa fa-qrcode"></i></button>
                                            @endif
                                        </div>        
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
     function confirmDelete() {
        if (confirm('Are you sure you want to delete this user?')) {
            document.getElementById('deleteForm').submit();
        }
    }
 
    function confirmAction(email) {
        var result = confirm("Are you sure you want to proceed?");
        if (result) {
            // User confirmed, perform the desired action
            $.ajax({
                url: '/admin/request_email', // Replace with your controller route
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}', // Include the CSRF token
                    requested_email:email
                    // Include any other data you want to send
                },
                success: function(response) {
                    alert("Action completed successfully!");
                    // Handle the success response
                },
                error: function(xhr, status, error) {
                    alert("An error occurred: " + xhr.responseText);
                    // Handle the error response
                    console.error(xhr, status, error);
                }
            });
        } else {
            // User canceled, do nothing
            alert("User canceled!");
        }
    }
</script>
@endpush