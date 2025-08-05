@extends('layout.app', ['activePage' => 'role', 'titlePage' => __('Roles List')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Roles List
                        @can('role.create')
                        <a href="{{ route('admin.role.create') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-plus"></i> Add Role</a>
                        @endcan
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
                                    <th scope="col" class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roles as $key => $role)
                                    @if (!(auth()->user()->email==='motor@fyntune.com')  && $role->name==='webservice')
                                        @continue
                                    @endif
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $role->name }}</td>
                                    <td>
                                        @if (!$loop->first)
                                        <form action="{{ route('admin.role.destroy', $role) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                            <div class="btn-group">
                                                {{--<!-- <a href="{{ route('admin.role.show', $role) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->--}}
                                                @can('role.edit')
                                                <a href="{{ route('admin.role.edit', $role) }}" class="btn btn-sm btn-outline-success show-template"><i class="fa fa-edit"></i></a>
                                                @endcan
                                                @can('role.delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                                @endcan
                                            </div>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {});
</script>
@endpush