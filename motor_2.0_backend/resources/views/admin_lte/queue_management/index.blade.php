@extends('admin_lte.layout.app', ['activePage' => 'queue management', 'titlePage' => __('Queue Management')])
@section('content')

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<div class='card card-primary'>
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="form-group">
                    <form name="{{route('admin.queue-management.index')}}" id="searchForm">
                        @csrf
                        <div class="row ml-3 mt-4">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="UUID">UUID</Label>
                                    <input id="UUID" value="{{ old('UUID', request()->UUID ?? null) }}" type="text" name="UUID" id="UUID" class="form-control" placeholder="Enquiry ID" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label for="Queue">Queue</Label>
                                    <select name="queue" id="queue" id="" class="form-control">
                                        <option value="">select</option>
                                        <option value="default" {{ old('queue', request()->queue) == 'default' ? 'selected' : '' }}>Default</option>
                                        <option value="server_1" {{ old('queue', request()->queue) == 'server_1' ? 'selected' : '' }}>Server 1</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label class="required" for="from">From Date</Label>
                                    <input id="from" required type="date" value="{{ old('from', request()->from) }}" name="from" id="from" class="datepickers form-control" placeholder="From" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label class="required" for="to">To Date</Label>
                                    <input id="to" required type="date" value="{{ old('to', request()->to) }}" name="to" id="to" class="datepickers form-control" placeholder="To" autocomplete="off" >
                                </div>
                            </div>
                        </div>
                        <div class="row ml-3 d-flex justify-content-start">
                            <div class="col-2">
                                <input type="submit" class="btn btn-outline-info btn-sm">
                            </div>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>
    <div class="row ml-8 d-flex justify-content-end ">
        <div class="col-2">
            <button type="button" class="btn btn-success btn-sm " onclick="RunSelectd('retry')">Sync Selectd</button>
            <button type="button" class="btn btn-danger btn-sm" onclick="RunSelectd('delete')">Delete Selectd</button>
        </div>
    </div>
</div>
<div class="table-responsive">
    @if (!empty($reports))
    <table class="table table-striped" id="QM_reports">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Select</th>
                <th scope="col">Action</th>
                <th scope="col">Uuid</th>
                <th scope="col">Name</th>
                <th scope="col">Queue</th>
                <th scope="col">Connection</th>
                <th scope="col">Failed_at</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($reports))
            @foreach($reports as $key => $value)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <th>
                    <input type="checkbox" id="{{ $value->uuid }}" name="vehicle1">
                </th>
                <td>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.queue-management.show', $value->id) }}" target="_blank"><i
                            class="fa fa-eye"></i>
                    </a>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.queue-management.edit', $value->uuid) }}"><i
                            class="fas fa-sync"></i>
                    </a>
                    <form action="{{ route('admin.queue-management.destroy', $value->uuid) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Are you sure you want to delete this item?');">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
                <td>{{ $value->uuid ?? '-' }}</td>
                <td>{{ substr(json_decode($value->payload)->displayName, 9) }}</td>
                <td>{{ $value->queue ?? '-' }}</td>
                <td>{{ $value->connection ?? '-' }}</td>
                <td>{{ $value->failed_at ?? '-' }}</td>
            </tr>
            @endforeach
            @endif
        </tbody>
    </table>
    @else
    <p style="text-align: center;">No Records found.</p>
    @endif
</div>
@endsection('content')
@section('scripts')
<script>
    $('#QM_reports').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": true,
    });

    function RunSelectd(action) {
        console.log(action);
        var selectedCheckboxes = [];
        $('input[type="checkbox"]:checked').each(function() {
            selectedCheckboxes.push($(this).attr('id'));
        });
        console.log(selectedCheckboxes);
        $.ajax({
            url: "{{ route('admin.queue-management.create') }}",
            method: 'GET',
            dataType: 'json',
            data: {
                selectedCheckboxes: selectedCheckboxes,
                action: action
            },
            success: function(data) {
                document.location.reload(true);
            }
        })
    }
</script>
@endsection('scripts')