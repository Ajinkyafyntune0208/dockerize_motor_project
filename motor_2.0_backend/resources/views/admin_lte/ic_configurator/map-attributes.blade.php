@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('MAPPING')])
@section('content')

<link rel="stylesheet" href="{{asset('css/mappedattribute.css')}}">

<section>
    <button class="btn btn-primary back-btn" id="save-btn" type="submit"><a href="{{ url('admin/ic-configuration/label-attributes') }}"><i class=" fa fa-solid fa-arrow-left"></i> BACK</a></button>
    <form action="" id="create-label">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-12 top-sectioin">
                    <div class="card">
                        <div class="card-header text-white ">
                            @foreach($labels as $data)
                            <input type="hidden" name="label_id" value="{{ $id }}" id="label_id">
                            <h6 class="label-display"> Label : <b>{{$data['label_name']}}</b></h6>
                            @endforeach()
                        </div>
                        <div class="card-body" id="form-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="selectIC" class="required_field">IC Integration</label>
                                        <select class="form-control get-value" id="selectIC" name="selectIC" required>
                                            <option value="">Select</option>
                                            @foreach($alias as $data)
                                            <option value="{{$data['ic_alias']}}#{{$data['integration_type']}}">{{$data['ic_alias']}} ({{$data['integration_type']}})</option>
                                            @endforeach()
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vehicle" class="required_field">Segment</label>
                                        <select class="form-control get-value" id="vehicle-data" name="vehicle-data" required>
                                            <option value="">Select</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="businessType" class="required_field">Business Type</label>
                                        <select class="form-control get-value" id="businessType" name="businessType" required>
                                            <option value="">Select</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group attribute-btn">
                                        <label for="attributes" class="required_field">Attribute</label>
                                        <select class="form-control selectpicker" id="attributes" name="attributes" data-live-search = "true" required>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary btn-place" id="save-btn" type="submit">Save</button>
                                </div>
                                <div class="col-md-3">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if(!empty($listing->all()))
    <div class="table-container" id="view-table">
        <table class="table table-bordered table-striped map-attribute-datatable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>IC Integration</th>
                    <th>Segment</th>
                    <th>Business Type</th>
                    <th>Attribute</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                @foreach($listing as $key => $searchData)
                <tr id="row-{{$searchData['id']}}">
                    <td>{{$key + 1}}</td>
                    <td class="company">{{$searchData['ic_alias']}}({{$searchData['integration_type']}})</td>
                    <td class="vehicle">{{$searchData['segment']}}</td>
                    <td class="business">{{$searchData['business_type']}}</td>
                    <td class="attribute">{{$searchData['final_attribute']}}</td>
                    <td>

                        <button type="button" class="btn btn-primary m-1 editModal" data-toggle="modal" data-target="#editModal" data-id="{{$searchData['id']}}" company_id="{{$searchData['ic_alias']}}#{{$searchData['integration_type']}}" vehicle="{{$searchData['segment']}}" business="{{$searchData['business_type']}}" attributeList="{{$searchData['final_attribute']}}" attribute_id="{{$searchData['id']}}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="" class="btn btn-success btn-danger delete-btn" id="{{ $id }}" attribute_id="{{$searchData['id']}}" label=""><i class=" fa fa-regular fa-trash"></i></button>

                    </td>
                    <!-- <td><input type="hidden" name="getID" id="getID" value="{{$searchData['id']}}"></td> -->
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
                    <th>IC Integration</th>
                    <th>Segment</th>
                    <th>Business Type</th>
                    <th>Attribute</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="6">No record found</td>
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
                        <h5 class="modal-title" id="editModalLabel">Edit Attribute</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="attribute_id" id="attribute_id" value=" {{ $id }}">
                        <input type="hidden" name="pre-attribute_id" id="pre-attribute_id">
                        <div class="form-group">
                            <label for="selectIC" class="required_field">IC Integration</label>
                            <select class="form-control get-value-data companyName" id="selectIC-edit" name="selectIC" required>
                                <option value="">Select</option>
                                @foreach($alias as $data)
                                <option value="{{$data['ic_alias']}}#{{$data['integration_type']}}">{{$data['ic_alias']}} ({{$data['integration_type']}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vehicle" class="required_field">Segment</label>
                            <select class="form-control get-value-data vehicleName" id="vehicle-edit" name="vehicle-edit" required>
                                <option value="">Select</option>
                                @foreach($segments as $key => $searchData)
                                <option value="{{$searchData['segment']}}">{{$searchData['segment']}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="businessType" class="required_field">Business Type</label>
                            <select class="form-control get-value-data businessName" id="businessType-edit" name="businessType-edit" required>
                                <option value="">Select</option>
                                @foreach($bussiness_type as $key => $searchData)
                                <option value="{{$searchData['business_type']}}">{{$searchData['business_type']}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editattributes" class="required_field">Attribute</label>
                            <select class="form-control get-value-data attributeName" id="editattributes" name="editattributes" required>
                                <option value="">Select</option>
                                <option value="subInsuranceProductCode - contract.subInsuranceProductCode">subInsuranceProductCode - contract.subInsuranceProductCode</option>
                            </select>
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
</section>

@endsection
 @section('scripts')
 <script>
    const saveAttributes = "{{ route('admin.ic-configuration.save-attributes') }}";
    const getAttribute = "{{ route('admin.ic-configuration.get-attribute') }}";
    const getEditAttribute = "{{ route('admin.ic-configuration.get-edit-attribute') }}";
    const editAttribute =  "{{ route('admin.ic-configuration.edit-attribute') }}";
    const deleteAttribute =  "{{ route('admin.ic-configuration.delete-attribute') }}";
 </script>
<script src="{{asset('admin1/js/ic-config/map.js')}}"></script>
@endsection