@extends('admin_lte.layout.app', ['activePage' => 'third_party_api_request_responses', 'titlePage' => __('Third Party Request Response Log')])
@section('content')
<!-- partial -->
<div class="card">
    <div class="card-body">
        <form action="">
            <div class="row">
                <div class="col-sm-3">
                    <label class="required" for="name">Name</label>
                    <select name="name" id="name" data-actions-box="true" class="select2 form-control w-100" data-live-search="true"  onChange=nameChange(event) required>
                        <option value=""></option>
                        @foreach($third_party_names as $name)
                        <option value="{{ $name->name }}" {{ (old('name', request()->name) == $name->name ? 'selected' : '') }}>{{ $name->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="url">Url</label>
                    <input type="text" name="url" class="form-control" value="{{ old('url', request()->url)}}" placeholder="URL" autocomplete="off" required>
                </div>
                <div class="col-md-3">
                    <label class="required" for="">From Date</label>
                    <input type="date" name="from" id="" value="{{ old('from', request()->from) }}" class="form-control" placeholder="From Date" autocomplete="off" required>
                </div>
                <div class="col-md-3">
                    <label class="required" for="">To Date</label>
                    <input type="date" name="to" id="" value="{{ old('to', request()->to) }}" class="datepickers form-control" placeholder="To Date" autocomplete="off" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary mt-3" id="submit">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(!empty($third_party_request_responses))
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped" id="response_log">
                <thead>
                    <tr>
                        <th scope="col">Sr. No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">url</th>
                        <th scope="col">Http Status</th>
                        <th scope="col">created_at</th>
                        <th scope="col">View More</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($third_party_request_responses as $key => $data)
                    <tr>

                        <td>{{ $third_party_request_responses->firstItem() + $key}}
                        <td>{{ $data->name }}</td>
                        <td>{{ substr($data->url, 0, 50) . '...' }}</td>
                        <td>{{ $data->http_status }}</td>
                        <td>{{ $data->created_at }}</td>
                        @can('third_party_api_responses.show')
                        <td> <a target="_blank" href="{{ url('admin/third_party_api_request_responses/'.$data->id)}}" class="btn btn-primary float-end btn-sm"><i class="fa fa-eye"></i></a></td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if (!request()->onChamge)
                {{ $third_party_request_responses->appends(Request::except('page'))->links() }}
            @endif
        </div>
    </div>
</div>
@endif

@endsection
@section('scripts')
<script>
    function nameChange(event){
            const selectedValue = event.target.value;
            window.location.replace(`third_party_api_request_responses?name=${selectedValue}&onChamge=true`);
        }
    $(document).ready(function() {
        $('.datepickers').datepicker({
            todayBtn: "linked",
            autoclose: true,
            clearBtn: true,
            todayHighlight: true,
            toggleActive: true,
            format: "yyyy-mm-dd",

        });

        $('#response_log').DataTable({
            "bPaginate": false,
            "paging": false,
            "bInfo": false
        });
    });
</script>
@endsection
