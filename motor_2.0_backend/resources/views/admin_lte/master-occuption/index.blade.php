@extends('admin_lte.layout.app', ['activePage' => 'master-occuption', 'titlePage' => __('Master Occupation')])
@section('content')
<div class="card">
    <div class="card-body">

        <div class="row">
            <div class="col-12">
                @can('master_occupation.create')
                <button type="button" class="btn btn-primary mb-3 occupationModal" style="float:right"><i class="fa fa-plus-square" aria-hidden="true"></i> Insert New Occupation</button>
                @endcan
            </div>
            <div class="col-12">
                <table id="data-table" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Actions</th>
                            <th>Sr. No.</th>
                            <th>Occupation Code</th>
                            <th>Occuptaion Name</th>
                            <th>Company Alias</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach($occuption as $key => $value)
                        <tr>
                            <td>
                                <div class="btn-group btn-group-toggle">
                                    @can('master_occupation.edit')
                                    <a class="btn btn-info mr-1" href="#" onclick = "editDetails('{{$value->occupation_code}}','{{$value->occupation_name}}','{{$value->company_alias}}','{{encrypt($value->id)}}')" data-toggle="modal" data-target="#modal-default" ><i class="far fa-edit"></i></a>
                                    @endcan
                                    @can('master_occupation.delete')
                                    <form action="{{ route('admin.ckyc_verification_types.destroy', encrypt($value->id)) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete')
                                        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                    @endcan
                                </div>

                            </td>
                            <td>{{++$key}}</td>
                            <td>{{$value->occupation_code}}</td>
                            <td>{{$value->occupation_name}}</td>
                            <td>{{$value->company_alias}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- edit Modal -->
<div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
<div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> Edit Occupation <span></span></h5>
                <!-- <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button> -->
            </div>
                <div class="modal-body">
                    <div class="form-group">

                        <form action="{{ route('admin.master-occupation.update', [1, 'data' => request()->all()]) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <input type="text" name="id" id="id" hidden/>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="occupation_code">Occupation Code <span class="text-danger"> *</span></label>
                                        <input type="text" class="form-control" name="occupation_code" id="occupation_code" required/>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="occupation_name">Occupation Name <span class="text-danger"> *</span></label>
                                        <select data-style="btn btn-primary" data-actions-box="true" class="selectpicker w-100 form-control" data-live-search="true" name="occupation_name" id="occupation_name" style="background-color: white;color: #404040;" required disabled>
                                        @foreach($occuption as $key => $data)
                                            <option value="{{ $data->occupation_name }}">{{$data->occupation_name }}</option>
                                        @endforeach
                                    </select>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="company_alias">Company Alias <span class="text-danger"> *</span></label>
                                        <select data-style="btn btn-primary" data-actions-box="true" class="selectpicker w-100 form-control" data-live-search="true" name="company_alias" id="company_alias" style="background-color: white;color: #404040;" required disabled>
                                            @foreach($company as $key => $datas)
                                                <option value="{{ $datas->company_alias}}">{{$datas->company_alias}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                                    <button type="submit" id="update-rto-btn" class="btn btn-primary mr-3" style="float:right">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                {{-- <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div> --}}

        </div>
    </div>
</div>


<!-- Add Modal -->
<div class="modal fade" id="occupationModal" tabindex="-1" aria-labelledby="rtoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rtoModalLabel"> Add New Occupation <span></span></h5>
                <!-- <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button> -->
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.master-occupation.store')}}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="occupation_code" class="required">Occupation Code</label>
                                <input type="text" class="form-control" name="occupation_code" id="occupation_code" required/>
                            </div>
                        </div>
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="occupation_name" class="required">Occupation Name</label>
                                <select data-style="btn btn-primary" data-actions-box="true" class="selectpicker w-100 form-control" data-live-search="true" name="occupation_name" id="occupation_name" style="background-color: white;color: #404040;" required>
                                    @foreach($Destinctoccuption as $key => $data)
                                        <option value="{{ $data->occupation_name }}">{{$data->occupation_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                            <div class="form-group">
                                <label for="company_alias" class="required">Company Alias</label>
                                <select data-style="btn btn-primary" data-actions-box="true" class="selectpicker w-100 form-control" data-live-search="true" name="company_alias" id="company_alias" style="background-color: white;color: #404040;" required>
                                    @foreach($company as $key => $datas)
                                        <option value="{{ $datas->company_alias}}">{{$datas->company_alias}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                            <button type="submit" id="update-rto-btn" class="btn btn-primary mr-3" style="float:right">Save</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
            </div>

        </div>
    </div>
</div>

@endsection('content')
@section('scripts')
<script>
  $(function () {
    $("#data-table").DataTable({
        "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:not(:nth-child(4))'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');

  });
  function editDetails(occupation_code,occupation_name,company_alias,id){
        $('#occupation_code').val(occupation_code);
        $('#occupation_name').val(occupation_name);
        $('#company_alias').val(company_alias);
        $('#id').val(id);
        // $('#data').selectpicker('refresh');
         $('#modal-default').modal("show");
    }

    $(".occupationModal").click(function(){
        $("#occupationModal").modal('show');
    })
</script>
@endsection('scripts')
