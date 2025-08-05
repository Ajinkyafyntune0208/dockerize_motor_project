@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css" />

<div class="content-wrapper">
    <div class="grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-9" style="margin-top: 14px;">
                        <h4 class="card-title">CKYC Not A Failure Cases</h4>
                    </div>
                </div>
                <form action="" method="GET" name="ckyc_failure">
                    @csrf
                    <input type="hidden" name="per_page" id="per_page" value="">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-group">
                                <input id="search" name="search" type="text"
                                    value="{{ old('search', request()->search ?? null) }}"
                                    class="form-control" placeholder="search by message" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                 <button class="btn btn-primary" id="submit">submit</button>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <a class="btn btn-primary" href="{{ route('admin.ckyc_not_a_failure_cases.create') }}">+ Add CKYC Failure message</i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                @php
                $perPageValues = [10,20,30,40,50];
                @endphp
                 <div class="d-flex col-1" style="padding-top:30px">
                    <label class="mx-2">Show</label>
                    <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 mx-2" data-live-search="true" style="width:100px;" name="per_page" id="per_page_hidden">
                        <option value="" {{ isset(request()->per_page) ? '' : 'selected'}}> Select</option>
                        @foreach ($perPageValues as $item)
                            <option value="{{$item}}" {{ old('per_page', request()->per_page ) == $item ? 'selected' : ($item == 10 ? 'selected' : '')   }}>{{$item}}</option>
                        @endforeach
                    </select>
                    <label>entries</label>
                </div>
                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table-striped table border">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($CKYCNotAFailureCasess as $case)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>
                                        <form action="{{ route('admin.ckyc_not_a_failure_cases.destroy', $case) }}"
                                            method="post" onsubmit="return confirm('Are you sure..?')"> @csrf
                                            @method('DELETE')
                                            <div class="btn-group">
                                                <a class="btn btn-warning p-0 btn-sm mr-1"
                                                    style="padding-right: 6px; padding-left: 10px;"
                                                    href="{{ route('admin.ckyc_not_a_failure_cases.edit', $case->id) }}"><i
                                                        class="fa fa fa-edit p-1"></i></a>
                                                <button class="btn btn-danger p-0 btn-sm mr-1 statusupdate"
                                                    type="submit" style="padding-left: 6px; padding-right: 10px;"><i
                                                        class="fa fa fa-trash p-1"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td scope="col">{{$case->type}}</td>
                                    <td scope="col">{{$case->message}}</td>
                                    <td scope="col">{{($case->active == 0) ? 'Inactive' : 'Active'}}</td>
                                    <td scope="col">{{$case->created_at}}</td>
                                    <td scope="col">{{$case->updated_at}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">
                        <p scope="col">Total Result found: {{$CKYCNotAFailureCasess->total()}}</p>
                        <p scope="col">Showing records per page: {{$CKYCNotAFailureCasess->count()}}</p>
                        <div scope="col">
                            @if(!$CKYCNotAFailureCasess->isEmpty())
                                {{ $CKYCNotAFailureCasess->appends(request()->query())->links() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script type="text/javascript" src="{{ asset('js/dataTables.buttons.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/jszip.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/buttons.html5.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/buttons.print.min.js') }}"></script>

<script>

    $(document).ready(function() {

        $('.table').DataTable({
            info: false,
            dom: 'lPfBrtpi',
            "iDisplayStart ": 10,
            "iDisplayLength": 10,
            "bPaginate": false, //hide pagination
            "bFilter": false, //hide Search bar
            "bInfo": false, // hide showing entries
            buttons: ['csv', 'excel'],
        });
        $('#per_page_hidden').on('change', function() {
                var selectedValue = $(this).val();
                var news = $('#per_page').val(selectedValue);
                $('#submit').click();
            });
    });
</script>
@endpush
