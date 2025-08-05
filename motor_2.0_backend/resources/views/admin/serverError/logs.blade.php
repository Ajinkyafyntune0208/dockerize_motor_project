@extends('layout.app', ['activePage' => 'Server Error Logs', 'titlePage' => __('Server Error Logs')])
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-uppercase"> Server Error Logs
                    </h4>
                    <p class="card-description"></p>
                    
                    <form action="" method="GET" name="serverErrorForm">
                        
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="active required" for="from">From Date</label>
                                    <input id="userInput" name="from" type="date" value="{{ old('userInput', request()->from ?? null) }}" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="active required" for="to">To Date</label>
                                    <input id="userInput" name="to" type="date" value="{{ old('userInput', request()->to ?? null) }}" class="form-control" required>
                                </div>
                            </div>
                            <input type="hidden" name="paginate" value="30">
                            
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <button type="submit"
                                class="btn btn-outline-info btn-sm w-100">
                                Submit <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
    </div>
    
    @if (count($data) > 0)
    @php
        $dropDownValues = [10,20,30,40,50];
    @endphp
    <div class="card">
        <div class="card-body">
            <div class="d-flex col-1" style="padding-top:30px">
                <label class="mx-2">Show</label>
                <select name="paginateDropdown" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 mx-2" data-live-search="true">
                    @foreach ($dropDownValues as $item)
                    <option value="{{$item}}" {{request('paginate') && request('paginate') == $item ? 'selected' : '' }}>{{$item}}</option>
                    @endforeach
                </select>
                <label>entries</label>
            </div>
            <div class="table-responsive">
                <table id='trace-enquiry-table' class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Source</th>
                            <th scope="col">Url</th>
                            <th scope="col">Request</th>
                            <th scope="col">Error</th>
                            <th scope="col">Created At</th>
                            <th scope="col">Updated At</th>
                        </tr>
                    </thead>
                    
                    <tbody>
                        @foreach ($data as $d)
                        <tr>
                            <td scope="row">{{ $loop->iteration }}</td>
                            <td>{{ strtoupper($d->source) }}</td>
                            <td>{{ $d->url ? url($d->url)  : 'NA'}}</td>
                            <td>{{ $d->request}}</td>
                            <td>{{ $d->error }}</td>
                            <td>{{ Carbon\Carbon::parse($d->created_at)->format('d-M-Y H:i:s A') }}</td>
                            <td>{{ Carbon\Carbon::parse($d->updated_at)->format('d-M-Y H:i:s A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">{{$data->appends(request()->query())->links()}}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @else
    <p style="font-size: 1rem;" class="card p-3 text-danger">
        No Records or search for records
    </p>
    @endif
</div>

@endsection

@push('scripts')
<script>
    window.onload=()=>{
        document.querySelector('[name="paginateDropdown"]').addEventListener('change', (e)=>{
            document.querySelector('[name="paginate"]').value=e.target.value

            document.querySelector('[name="serverErrorForm"]').submit();
        })

        $('.table').DataTable({
            paging: false,
            ordering: false,
            info: false,
        });
    }
</script>
@endpush