@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('PLACEHOLDER')])
@section('content')

<link rel="stylesheet" href="{{asset('css/placeholder.css')}}">

<section>
    <form action="" id="create-label">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-12 top-sectioin">
                    <div class="card">
                        <div class="card-body" id="form-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="selectIC" class="required_field">Placeholder Name</label>
                                        <input type="text" class="form-control get-value-data commentData" id="holderName" name="holderName" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vehicle" class="required_field">Placeholder Key</label>
                                        <input type="text" class="form-control get-value-data commentData" id="holderKey" name="holderKey" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary btn-place" id="save-btn" type="submit">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if(!empty($placeholderData))
    <div class="table-container" id="view-table">
        <table class="table table-bordered table-striped table-placeholder">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Placeholder Name</th>
                    <th>Placeholder Key</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($placeholderData as $key => $searchData)
                <tr id="row-{{$searchData['id']}}">
                    <td>{{$key + 1}}</td>
                    <td class="placeholderName">{{$searchData['placeholder_name']}}</td>
                    <td class="placeholderKey">{{$searchData['placeholder_key']}}</td>
                    <td class="placeholderKey">{{$searchData['placeholder_type_show']}}</td>
                    <td>
                        @if (auth()->user()->can('ic_placeholder.show'))
                        <button type="" class="btn btn-secondary btn-danger myModalShow m-1" data-toggle="modal" data-target="#myModalShow" pName="{{$searchData['placeholder_name']}}" kName="{{$searchData['placeholder_key']}}"  attribute_id="{{$searchData['id']}}" label=""><i class="fa fa-eye" aria-hidden="true"></i></button>
                        @endif 
                        @if (auth()->user()->can('ic_placeholder.edit'))
                        @if ($searchData['placeholder_type'] != 'system')
                        <button type="button" class="btn btn-primary m-1 editModal" data-toggle="modal" data-target="#editModal" data-id="{{$searchData['id']}}" pName="{{$searchData['placeholder_name']}}" kName="{{$searchData['placeholder_key']}}">
                            <i class="fas fa-edit"></i>
                        </button>
                        @endif
                        @endif
                        @if (auth()->user()->can('ic_placeholder.delete'))
                        @if ($searchData['placeholder_type'] != 'system')
                        <button type="" class="btn btn-success btn-danger delete-btn" attribute_id="{{$searchData['id']}}" label=""><i class=" fa fa-regular fa-trash"></i></button>
                        @endif
                        @endif
                                             
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="table-container" id="view-table">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Placeholder Name</th>
                    <th>Placeholder Key</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="5">No record found</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="save-editForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit</h5>
                    </div>
                    <input type="hidden" class="form-control" id="holderID" name="holderID">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="holder-name" class="required_field">Placeholder Name</label>
                            <input type="text" class="form-control" id="holder-name" name="holder-name">
                        </div>
                        <div class="form-group">
                            <label for="holder-key" class="required_field">Placeholder Key</label>
                            <input type="text" class="form-control" id="holder-key" name="holder-key">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">CLOSE</button>
                        <button type="submit" class="btn btn-primary">UPDATE</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!--end edit model -->

    {{-- view --}}
    <div class="modal fade" id="myModalShow" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">View Placeholder  </h5>
                </div>
                <div class="modal-body">
                    <h6 class="data-display">Placeholder Name : <label for="" class="pname"></label> </h6>
                    <h6 class="data-display">Placeholder Key : <label for="" class="kname"></label> </h6>
                    <label for=""> Formula List : </label>
                    <ul> 
                    <li class="dataListFormula"></li>
                   </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-btn" id='modal-close' data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</section>
@endsection

@section('scripts')
<script>
   const savePlaceholder = "{{ route('admin.ic-configuration.placeholder.save-placeholder') }}";
   const deletePlaceholder = "{{ route('admin.ic-configuration.placeholder.delete-placeholder') }}";
   const editPlaceholder = "{{ route('admin.ic-configuration.placeholder.edit-placeholder') }}";
   const showPlaceholder = "{{ route('admin.ic-configuration.placeholder.show-placeholder') }}";
</script>
<script src="{{asset('admin1/js/ic-config/placeholder.js')}}"></script>
@endsection