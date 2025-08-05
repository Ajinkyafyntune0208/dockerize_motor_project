@extends('layout.app', ['activePage' => 'activity-logs', 'titlePage' => __('Activity Logs')])
@section('content')
<style>
</style>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Activity Logs</h5>
                    <form action="" method="GET" name="ActivityFrom">
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
                        
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active" for="from">From</label>
                                    <input id="from" name="from" type="date" value="{{ old('from', request()->from ?? null) }}" class="form-control" required placeholder="Trace Id / RC Number">
                                </div>
                            </div>
                            
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active" for="to">To</label>
                                    <input id="to" name="to" type="date" value="{{ old('to', request()->to ?? null) }}" class="form-control" required placeholder="Trace Id / RC Number">
                                </div>
                            </div>
                            
                            <input type="hidden" name="paginate" value="30">
                            
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active" for="type">Type</label>
                                    <select name="type[]" multiple data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" required>
                                        @foreach ($types as $item)
                                        <option value="{{$item}}" {{ request()->type && in_array($item, request()->type) ? 'selected' : ''  }}>{{$item}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3 text-right">
                                <div class="form-group">
                                    <button class="btn btn-outline-primary" type="submit" style="margin-top: 30px;"><i
                                        class="fa fa-search"></i> Search</button>
                                    </div>
                                </div>
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-12 grid-margin stretch-card">
                @if (count($activities)>0)
                @php
                $dropDownValues = [10,20,30,40,50];
                @endphp
                <div class="card">
                    <div class="card-body">
                        Show
                        <select name="paginateDropdown" style="width:50px;opacity:0.7;border-radius:3px;margin-right:5px;margin-left:5px">
                            @foreach ($dropDownValues as $item)
                            <option value="{{$item}}" {{request('paginate') && request('paginate') == $item ? 'selected' : '' }}>{{$item}}</option>
                            @endforeach
                        </select>
                        entries
                        <div class="table-responsive">
                            <table class="table-striped table">
                                <thead>
                                    <tr>
                                        <th>Date and Time</th>
                                        <th>Type</th>
                                        <th>Operation</th>
                                        <th>Logs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activities as $item)
                                    <tr>
                                        <td>{{ date('dS F Y h:i:s A', strtotime($item['created_at'])) }}</td>
                                        <td> {{ $item['service_type'] }}</td>
                                        <td> {{ $item['operation'] }}</td>
                                        <td>
                                            @switch($item['operation'])
                                            @case('CREATED')
                                            {{$item['new_data']}}
                                            @break
                                            @case('UPDATED')
                                            Old Data : {{$item['old_data']}} <br><br>
                                            New Data : {{$item['new_data']}}
                                            @break
                                            @case('DELETED')
                                            {{$item['old_data']}}
                                            @break
                                            @default
                                            
                                            @endswitch
                                        </td>
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
            </div>
        </div>
    </div>
    @endsection

    @push('scripts')
<script>
    window.onload=()=>{
        document.querySelector('[name="paginateDropdown"]').addEventListener('change', (e)=>{
            document.querySelector('[name="paginate"]').value=e.target.value
            
            document.querySelector('[name="ActivityFrom"]').submit();
        })
        
        
        $('.table').DataTable({
            paging: false,
            ordering: false,
            info: false,
        });
    }
</script>
@endpush