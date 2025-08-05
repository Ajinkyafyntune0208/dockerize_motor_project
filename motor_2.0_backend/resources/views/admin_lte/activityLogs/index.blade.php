@extends('admin_lte.layout.app', ['activePage' => 'user-activity-logs', 'titlePage' => __('User Activity Logs')])
@section('content')
@if(Session::has('error'))
<div class="alert alert-danger">
    {{ Session::get('error') }}
    @php
    Session::forget('error');
    @endphp
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="list">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<div class="card">
    <div class="card-body">
        <form action="" method="GET" name="ActivityFrom">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="operation">Operation</label>
                        <select name="operation[]" multiple data-style="btn btn-light" data-actions-box="true" class="border selectpicker w-100" data-live-search="true" data-placeholder="select an operations ">
                            @foreach ($operations as $op)
                            <option value="{{$op}}" {{ request()->operation && in_array($op, request()->operation) ? 'selected' : ''  }}>{{$op}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if(auth()->user()->hasRole('Admin'))
                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="users">Users</label>
                        <select name="users[]" multiple data-style="btn btn-light" data-actions-box="true" class="border selectpicker w-100" data-live-search="true" aria-hidden="true" data-placeholder="select users" required>
                            @foreach ($users as $user)
                            <option value="{{$user['id']}}" {{ request()->users && in_array($user['id'], request()->users) ? 'selected' : ''  }}>{{$user['name']}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="from">From</label>
                        <input id="from" name="from" type="date" value="{{ old('from', request()->from ?? null) }}" class="form-control" required placeholder="From Date">
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="to">To</label>
                        <input id="to" name="to" type="date" value="{{ old('to', request()->to ?? null) }}" class="form-control" required placeholder="To Date">
                    </div>
                </div>

                <input type="hidden" name="paginate" value="30">
            </div>
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="type">Type</label>
                        <select name="type[]" multiple data-style="btn btn-light" data-actions-box="true" class="border selectpicker w-100" data-live-search="true" data-placeholder="select type" required>
                            @foreach ($types as $item)
                            <option value="{{$item}}" {{ request()->type && in_array($item, request()->type) ? 'selected' : ''  }}>{{$item}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active" for="commit_id">Commit ID</label>
                        <input id="commit_id" name="commit_id" type="text" value="{{ old('commit_id', request()->commit_id ?? null) }}" class="form-control" placeholder="Search by commit id">
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active" for="session_id">Session ID</label>
                        <input id="session_id" name="session_id" type="text" value="{{ old('session_id', request()->session_id ?? null) }}" class="form-control" placeholder="Search by session id">
                    </div>
                </div>

                <div class="col-sm-3 text-right">
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit" style="margin-top: 30px;"><i
                            class="fa fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@if (count($activities)>0)
@php
$dropDownValues = [10,20,30,40,50];
$colours = [
    'DELETED' => 'text-danger',
    'UPDATED' => 'text-warning',
    'CREATED' => 'text-success',
    ];
@endphp
<div class="card w-100">
    <div class="card-body">
        <!-- Show
        <select name="paginateDropdown" style="width:50px;opacity:0.7;border-radius:3px;margin-right:5px;margin-left:5px">
            @foreach ($dropDownValues as $item)
            <option value="{{$item}}" {{request('paginate') && request('paginate') == $item ? 'selected' : '' }}>{{$item}}</option>
            @endforeach
        </select>
        entries -->
        <div class="table-responsive">
            <table class="table-striped table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Operation</th>
                        <th>User Name</th>
                        <th>Date and Time</th>
                        <th>Type</th>
                        <!-- <th>Commit ID</th>
                        <th>Session ID</th> -->
                        <th>Logs</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $item)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td class="{{ $colours[$item['operation']] ?? '' }}"> {{ $item['operation'] }}</td>
                        <td>{{ $item->user->name ?? NA }}</td>
                        <td>{{ date('dS M Y h:i:s A', strtotime($item['created_at'])) }}</td>
                        <td> {{ $item['service_type'] }}</td>
                        <!-- <td> {{ $item['commit_id'] }}</td>
                        <td> {{ $item['session_id'] }}</td> -->
                        <td>
                            @switch($item['operation'])
                            @case('UPDATED')
                            @if(count(json_decode($item['new_data'], true)) > 2)
                                @php
                                    $route = route('admin.user-activity-logs.show', ['user_activity_log' => $item]);
                                @endphp
                            <!-- <button type="button" class="btn btn-sm btn-primary" onclick="openModal(event, '{{ $route }}')"><i class="fa fa-eye"></i></button> -->
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openModal(event, '{{ $route }}')">
            <i class="fa fa-eye"></i>
        </button>

                            @else
                                <table class="inner-table">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            @php
                                            $oldData = json_decode($item['old_data'], true);
                                            $newData = json_decode($item['new_data'], true);
                                            @endphp
                                            @foreach (array_keys($oldData) as $oldItem)
                                            <th>{{ $oldItem }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Old Data</td>
                                            @foreach (array_values($oldData) as $oldValue)
                                                <td class="text-warning">&nbsp;{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</td>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            <td>New Data</td>
                                            @foreach (array_values($newData) as $newValue)
                                                <td class="text-success">&nbsp;{{ is_array($newValue) ? json_encode($newValue) : $newValue }}</td>
                                            @endforeach
                                        </tr>
                                    </tbody>
                                </table>
                            @endif
                            @break
                            @case('CREATED')
                            @case('DELETED')
                                @php
                                    $route = route('admin.user-activity-logs.show', ['user_activity_log' => $item]);
                                @endphp
                                   @can('user_activity_logs.show')
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#show-user-activity-model" onclick="openModal(event, '{{ $route }}' )">
                                <i class="fa fa-eye"></i>
                                </button>
                                @endcan
                            @break
                            @default

                            @endswitch
                        </td>
                        <td> {{ $item['ip'] }} </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">{{$activities->appends(request()->query())->links()}}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@else
<p>No Records or search for records</p>
@endif

<!-- <div class="modal fade" id="show-user-activity-model" data-bs-toggle="modal" tabindex="-1" role="dialog" aria-labelledby="UserActivityLog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="UserActivityLog">User Activity</h5>
                <button type="button" class="btn btn-sm close" data-dismiss="modal"><i class="menu-icon mdi mdi-window-close"></i></button>
            </div>
            <div class="modal-body">
                <div class="spinner-border text-primary" style="top:50%; left:50%" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <iframe id="iframe" style="display:none;" onload="stopSpinner()" src="" frameborder="0" width="100%" height="100%"></iframe>
            </div>
            <div class="modal-footer">
            </div>
        </div>
    </div>
</div> -->

<!-- Modal for displaying user activity -->
<div class="modal fade" id="show-user-activity-model" data-bs-toggle="modal" tabindex="-1" role="dialog" aria-labelledby="UserActivityLog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="UserActivityLog">User Activity</h5>
                <button type="button" class="btn btn-lg close" data-dismiss="modal">
                   <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="spinner-border text-primary" style="top:50%; left:50%" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <!-- This div will be used to display the fetched content -->
                <div id="user-activity-content" style="display: none;"></div>
            </div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

    @endsection

    @section('scripts')
<script>

$(document).ready(function() {
        $('.table').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "scrollX": false
        });
    });

    function openModal(event, src) {
        event.preventDefault();
       
        window.open(src, '_blank'); // Open the URL in a new tab
    }


    $(document).ready(function() {
        $('.close').click(function(e) {
            e.preventDefault();
            $('#show-user-activity-model').modal("hide");
        });
    });

</script>
@endsection