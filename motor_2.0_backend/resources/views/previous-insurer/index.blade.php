@extends('layout.app', ['activePage' => 'previous-list', 'titlePage' => __('PreviousList')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Previous Insurer Logo</h5>

                        <!-- tractor_manufacturer -->
                        <!-- pcv_manufacturer -->
                        <!-- motor_manufacturer -->
                        <!-- bike_manufacturer -->
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="gcv_manufacturer" role="tabpanel" aria-labelledby="gcv_manufacturer-tab">
                                <div class="row mt-4">
                                    <div class="col-12 d-flex justify-content-end stretch-card">
                                    </div>
                                </div>
                                @if (session('status'))
                                    <div class="alert alert-{{ session('class') }}">
                                        {{ session('status') }}
                                    </div>
                                @endif
                                <div class="table-responsive">
                                    <table class="table table-bordered mt-3">
                                        <thead>
                                        <tr>
                                            <th scope="col">Previous Insurer</th>
                                            <th scope="col">company_alias</th>
                                            <th scope="col">Logo</th>
                                            <th scope="col">Action</th>
                                        </tr    >
                                        </thead>
                                        <tbody>
                                        @foreach($previousinsurer as $key => $row)
                                                                                     <tr>
                                                <td scope="col">{{ $row->previous_insurer }}</td>
                                                <td scope="col">{{ $row->company_alias }}</td>
                                                <td scope="col"><img src="{{$row->logo}}" class="img-fluid" style=""></td>
                                                <td scope="col" class="text-right">
                                                    @can('previous_insurer.edit')
                                                  <div class="btn-group">
                                                        <!-- <a href=" route('admin.usp.show', [$car_usp->car_usp_id, 'usp_type' => 'car' ]) " class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a> -->
                                                            <!-- @ can('usp.edit') -->
                                                            <a href="#" data-bs-toggle="modal" data-bs-target="#exampleModal" class="btn btn-success upload-logo-modal"  data-logo-path="previous_insurer_logos" data-logo-name="{{ Str::camel($row->company_alias) . '.png'}}"><i class="fa fa-edit"></i></a>
                                                            <!-- @ endcan -->
                                                            <!-- @ can('usp.delete') -->
                                                            <!-- <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button> -->
                                                            <!-- @ endcan -->
                                                        </div>
                                                    @endcan

                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>
                    </div>
                </div>

                <!--  -->
                <!-- Modal -->
                <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('admin.previous-insurer.store') }}" enctype="multipart/form-data" method="post" id="update-logo">@csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Status</h5>
                                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="file" class="required">Previous Insurer Logo</label>
                                        <input type="file" class="btn btn-primary" name="image" accept=".png" required>
                                        <input type="hidden" name="name" id="name">
                                        <input type="hidden" name="path" id="path">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--  -->
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.table').DataTable();
            $(document).on('click', '.upload-logo-modal', function() {
                $('#name').val($(this).attr('data-logo-name'));
                $('#path').val($(this).attr('data-logo-path'));
            });
        });
    </script>
@endpush
