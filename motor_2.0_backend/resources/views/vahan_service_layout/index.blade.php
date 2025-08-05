@extends('vahan_service_layout.app')
@section('content')
<style>

    @media (min-width: 576px){
        .modal-dialog {
            max-width: 911px;
            margin: 34px auto;
            word-wrap: break-word;
        }
    }

</style>
<div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        @if($cred == 'Y')
                        <h5 class="card-title">Vahan Service Credentials</h5>
                        @else
                        <h5 class="card-title">Vahan Service
                            <a href="{{ route('admin.vahan_service.create') }}" class="btn btn-primary btn-sm float-end">Add
                                New Service</i></a>
                        </h5>
                        @endif
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        @if (!empty($vahan_Services))
                            <div class="table-responsive">
                                <table class="table table-striped" id="vahan_service_table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Vahan service name</th>
                                            <th scope="col">Vahan service code</th>
                                            <th scope="col">Status</th>
                                            <th scope="col" class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($vahan_Services as $key => $vahan_Service)
                                            @if (!empty($vahan_Service))
                                                <tr>
                                                    <td scope="col">{{ $vahan_Service->vahan_service_name }}</td>
                                                    <td scope="col">{{ $vahan_Service->vahan_service_name_code }}</td>
                                                     <td scope="col" class="text-right">
                                                        <span
                                                            class="badge badge-{{ $vahan_Service->status == 'Active' ? 'success' : 'danger' }}">{{ $vahan_Service->status == 'Active' ? 'Active' : 'Inactive' }}</span>
                                                    </td>
                                                    @if($cred == 'Y')
                                                    <td scope="col">
                                                            <a class="btn btn-primary btn-sm"
                                                                href="{{ route('admin.vahan_credentials.edit', $vahan_Service) }}"
                                                                title="Edit"><i class="fa fa-pencil-square-o"
                                                                    aria-hidden="true"></i></a>
                                                            <a href="#" class="view text-dark" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal" data="{{$vahan_Service->id}}"><i style="font-size: 1.2rem;" class="fa fa-eye"></i></a>
                                                                    <form method="post" action="{{ route('admin.vahan_credentials.delete', ['id' => $vahan_Service, 'cred' => $cred]) }}" accept-charset="UTF-8" style="display:inline">
                                                                        {{ method_field('DELETE') }}
                                                                        {{ csrf_field() }}
                                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Product" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                                                                    </form>
                                                    </td>

                                                    @else
                                                    <td scope="col">
                                                        <a class="btn btn-primary btn-sm"
                                                            href="{{ route('admin.vahan_service.edit', $vahan_Service) }}"
                                                            title="Edit"><i class="fa fa-pencil-square-o"
                                                                aria-hidden="true"></i></a>
                                                                <form method="post" action="{{ route('admin.vahan_credentials.delete', ['id' => $vahan_Service, 'cred' => $cred]) }}" accept-charset="UTF-8" style="display:inline">
                                                                    {{ method_field('DELETE') }}
                                                                    {{ csrf_field() }}
                                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Product" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                                                                </form>
                                                </td>
                                                @endif
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
</div>
{{-- Modal --}}
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> Edit Occuption <span></span></h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">
                            <div class="row">
                                <input type="text" name="id" id="id"  hidden/>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="label">Label</label>
                                        <input type="text" class="form-control" name="label" id="label" readonly/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="key">Key</label>
                                        <input type="text" class="form-control" name="key" id="key" readonly/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="value">Value</label>
                                        <input type="text" class="form-control" name="value" id="value" readonly/>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
    <script>
        $(document).on('click', '.view', function () {
        var cred_id = JSON.parse($(this).attr('data'));

        $('#exampleModalLabel').html(`Showing Service Details:`);
        $.ajax({
            type: "get",
            url: "/cred_show/"+cred_id,
            success: function (response) {
               console.log(response);
               $("#label").val(response.data.label);
               $("#key").val(response.data.key);
               $("#value").val(response.data.value);
            }
        });
    });

        $(document).ready(function() {
            $('#vahan_service_table').DataTable();

        });
    </script>
@endpush
