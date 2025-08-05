@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css" />

<div class="content-wrapper">
    <div class="grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-9" style="margin-top: 14px;">
                    @error('search')
                        <div class="alert alert-danger">{{ $message}}</div>
                     @enderror
                        <h4 class="card-title">Ckyc Verification Types</h4>
                    </div>
                </div>
                <form action="" method="GET" name="ckyc_verification">
                    @csrf
                    <input type="hidden" name="per_page" id="per_page" value="">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-group">
                                <input id="search" name="search" type="search"
                                    value="{{ old('search', request()->search ?? null) }}" class="form-control"
                                    placeholder="search by message "required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <button class="btn btn-primary" id="submit">submit</button>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <a href="{{ route('admin.ckyc_verification_types.create') }}" class="btn btn btn-primary"
                                target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal">Add Ckyc
                                verification </a>
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
                        <option value="{{$item}}" {{ old('per_page', request()->per_page ) == $item ? 'selected' :
                            ($item == 10 ? 'selected' : '') }}>{{$item}}</option>
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
                                    <th scope="col">Company_alias</th>
                                    <th scope="col">Mode</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ckyc_verification_data as $case)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>
                                        <form
                                            action="{{ route('admin.ckyc_verification_types.destroy', encrypt($case->id)) }}"
                                            method="post" onsubmit="return confirm('Are you sure..?')"> @csrf
                                            @method('DELETE')

                                            <div class="btn-group">
                                                <a class="btn btn-warning p-0 btn-sm mr-1"
                                                    style="padding-right: 6px; padding-left: 10px;"><i
                                                        class="fa fa fa-edit p-1"
                                                        onclick="editDetails('{{$case->company_alias}}','{{$case->mode}}','{{encrypt($case->id)}}')"
                                                        data-bs-toggle="modal" data-bs-target="#myModal"></i></a>
                                                {{-- <input type="hidden" class="form-control" id="id" name="new"
                                                    value="{{$case->id}}"> --}}
                                                <button class="btn btn-danger p-0 btn-sm mr-1 statusupdate"
                                                    type="submit" style="padding-left: 6px; padding-right: 10px;"><i
                                                        class="fa fa fa-trash p-1"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td scope="col">{{$case->company_alias}}</td>
                                    <td scope="col">{{$case->mode}}</td>
                                    <td scope="col">{{$case->created_at}}</td>
                                    <td scope="col">{{$case->updated_at}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">
                        <p scope="col">Total Result found: {{$ckyc_verification_data->total()}}</p>
                        <p scope="col">Showing records per page: {{$ckyc_verification_data->count()}}</p>
                        <div scope="col">
                            @if(!$ckyc_verification_data->isEmpty())
                            {{ $ckyc_verification_data->appends(request()->query())->links() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal For Adding Data-->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add Ckyc Verification Type</h5>
                            <form action="{{ route('admin.ckyc_verification_types.store') }}" method="POST" class="mt-3"
                                name="add_config">
                                @csrf @method('POST')
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="active required" for="label">Company Alias</label>
                                            <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" aria-label="Default select"
                                                name="company_alias" required>
                                                <option value="" selected>Select an option</option>
                                                @foreach ($company_alias as $data)
                                                <option value="{{$data->company_alias}}">
                                                    {{$data->company_alias}}</option>
                                                @endforeach
                                            </select>
                                            @error('type')<span class="text-danger">{{ $message
                                                }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="active required" for="label">Mode</label>
                                            <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" aria-label="Default select" name="mode" required>
                                                <option value="" selected> select </option>
                                                <option value="redirection">Redirection</option>
                                                <option value="api">Api</option>
                                            </select>
                                            @error('active')<span class="text-danger">{{ $message
                                                }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-outline-primary w-25"
                                            style="margin-top: 30px;">Submit</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal model for edit and update -->
<div id="myModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Ckyc Verification Types</h5>
                            <form action="{{ route('admin.ckyc_verification_types.update',encrypt($case->id)) }}" {{--
                                {{dd(route('admin.ckyc_verification_types.update',$case->id))}} --}}
                                method="POST" class="mt-3" name="add_config">
                                @csrf @method('PUT')
                                <input type="hidden" class="form-control" id="id" name="id">
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="active required" for="label">Company Alias</label>
                                            <input type="text" class="form-control" id="company_alias"
                                                name="company_alias" disabled>
                                            @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label class="active required" for="label">Mode</label>
                                            <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" aria-label="Default select" id="data"
                                                name="mode">
                                                <option value="api">Api</option>
                                                <option value="redirection">Redirection</option>
                                            </select>
                                            @error('active')<span class="text-danger">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-outline-primary"
                                            style="margin-top: 30px;">Submit</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
@push('scripts')
<script type="text/javascript" src="{{ asset('js/pdfmake.min.js') }}"></script>
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
<script>
    // var get_data = new XMLHttpRequest();
    // get_data.open("POST","/update_ckyc_verification./",true);
    // get_data.send();

        function editDetails(company_alias,mode,id){
        $('#company_alias').val(company_alias);
        $('#modenew').val(mode);
        $('#id').val(id);
        $('#data').val(mode);
        $('#data').selectpicker('refresh');
        $('#myModal').modal("show");
    }
</script>


@endpush
