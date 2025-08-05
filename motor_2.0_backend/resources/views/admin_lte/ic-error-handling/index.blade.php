@extends('admin_lte.layout.app', ['activePage' => 'ic-error-handling', 'titlePage' => __('IC Error Handler')])
@section('content')
<style>
    @media (min-width: 576px) {
        .modal-dialog {
            max-width: 662px;
        }
    }
</style>
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                @can('ic_error_handler.create')
                <a href="{{ route('admin.ic-error-handling.create') }}" class="btn btn-sm btn-primary justify-content-end"><i class="fa fa-plus-circle"></i> Add</a>&nbsp;
                @endcan
                <a href="{{ route('admin.ic-error-handling.create',['file'=>'csv_file']) }}" class="btn btn-sm btn-primary justify-content-end"><i class="fa fa-plus-circle"></i> Upload CSV</a>
                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-end stretch-card">

                    </div>
                </div>
                <!-- start search records section-->
                <div class="form-group">
                    <form action="" method="GET">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Company Name</label>
                                    <select name="company_alias" id="company_alias" data-style="btn-primary btn-sm" required class="form-control select2" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        @foreach($companies as $company)
                                        <option {{ old('company_alias', request()->company_alias) == $company->company_alias ? 'selected' : '' }}>{{ $company->company_alias}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Section</label>
                                    <select name="section" id="section" data-style="btn-primary btn-sm" required class="form-control select2" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        <option value="car" {{ old('section', request()->section) == 'car' ? 'selected' : '' }}>CAR</option>
                                        <option value="bike" {{ old('section', request()->section) == 'bike' ? 'selected' : '' }}>BIKE</option>
                                        <option value="cv" {{ old('section', request()->section) == 'cv' ? 'selected' : '' }}>CV</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label>Select type</Label>
                                    <select name="type" id="type" data-style="btn-primary btn-sm" required class="form-control select2" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        <option value="quote" {{ old('type', request()->type) == 'quote' ? 'selected' : '' }}>Quote</option>
                                        <option value="proposal" {{ old('type', request()->type) == 'proposal' ? 'selected' : '' }}>Proposal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <Label>Status</Label>
                                    <select name="status" id="status" data-style="btn-primary btn-sm" required class="form-control select2" data-live-search="true">
                                        <option value="" selected>Select Any One</option>
                                        <option value="Y" {{ old('status', request()->status) == 'Y' ? 'selected' : '' }}>Active</option>
                                        <option value="N" {{ old('status', request()->status) == 'N' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center h-100">
                                    <input type="submit" class="form-control select2 btn btn-outline-primary w-75 mt-3">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- end of search records section -->
                <div class="table-responsive">
                    <table class="table table-striped" id="push_data_api_table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Company</th>
                                <th scope="col">Section</th>
                                <th scope="col">IC Error</th>
                                <!-- <th scope="col">Custom Error</th>  -->
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($data->isEmpty())
                                <tr class="align-items-center">
                                    <td colspan="6" >No Records found</td>
                                </tr>
                            @else
                                @foreach($data as $d)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $d->company_alias }}</td>
                                    <td>{{ $d->section }}</td>
                                    <td>{{ substr($d->ic_error, 0 ,95) }}</td>
                                    <!-- <td>{{$d->custom_error}}</td>  -->
                                    <td>{{$d->status}}</td>
                                    <td scope="col">
                                        <form action="{{ route('admin.ic-error-handling.destroy', [$d->id, 'type' => request()->type]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                            <div class="btn-group">
                                                @can('ic_error_handler.edit')
                                                <a href="{{ route('admin.ic-error-handling.edit', [$d->id, 'type' => request()->type]) }}" class="btn btn-sm btn-success"><i class="fa fa-edit"></i></a>
                                                @endcan
                                                @can('ic_error_handler.show')
                                                <a href="#" data-toggle="modal" data-target="#exampleModal" data="{{ $d->ic_error }}" custom_msg="{{ $d->custom_error }}" status="{{ $d->status }}" class="btn btn-sm btn-warning view"><i class="fa fa-eye"></i></a>
                                                @endcan
                                                @can('ic_error_handler.delete')
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                                @endcan
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="float-end mt-1">
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </section>
</main>


@endsection

@section('scripts')
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> View Error<span></span></h5>
                <button type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <h2>IC Error</h2>
                    <hr>

                    <span id="showdata"></span> <br><br>

                    <hr>
                    <h2>Custom Error</h2>

                    <span id="showresponse"></span>

                </div>
            </div>
            <div class="modal-footer">
                <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
            </div>

        </div>
    </div>
</div>

<!-- <script>
    $(document).ready(function() {

        $('#push_data_api_table').DataTable({
            "ordering": false
        });
            }
        );
</script> -->

<script>
    $(document).on('click', '.view', function() {
        var data = $(this).attr('data');
        var custom_msg = $(this).attr('custom_msg');
        $('#showdata').html(data);
        $('#showresponse').html(custom_msg);
    });
</script>

@endsection('scripts')
