@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('ADD VERSION DATA')])
@section('content')

<link rel="stylesheet" href="{{asset('css/icversionconfigurator.css')}}">

<button class="btn btn-primary back-btn" id="save-btn" type="submit"><a href="{{ url('admin/ic-configuration/version/ic-version-configurator') }}"><i class=" fa fa-solid fa-arrow-left"></i> BACK</a></button>
<section>
    <form action="" id="create-label">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-12 top-sectioin">
                    <div class="card">
                        <div class="card-body" id="form-body">
                            <div class="">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="selectIC" class="required_field">Insurance Company</label>
                                        <select class="form-control get-value" id="selectIC" name="selectIC" required>
                                            <option value="">Select</option>
                                            @foreach($alias as $data)
                                            <option value="{{$data['company_alias']}}">{{$data['company_name']}}</option>
                                            @endforeach()
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">  
                                    <div class="form-group">
                                        <label for="type" class="required_field">Integration Type</label>
                                        <select class="form-control get-value" id="type" name="type" required>
                                            <option value="">Select</option>
                                            @foreach($integrationType as $data)
                                            <option value="{{$data['integration_type']}}">{{$data['integration_type']}}</option>
                                            @endforeach()
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2">  
                                    <div class="form-group">
                                        <label for="business" class="required_field">Business Type</label>
                                        <select class="form-control get-value" id="business" name="business" required>
                                            <option value="">Select</option>
                                            @foreach($businessType as $data)
                                            <option value="{{$data['business_type']}}">{{$data['business_type']}}</option>
                                            @endforeach()
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vehicle-data" class="required_field">Version</label>
                                        <select class="form-control get-value" id="vehicle-data" name="vehicle-data" required>
                                            <option value="">Select</option>
                                            <option value="1">V1</option>
                                            <option value="2">V2</option>
                                            <option value="3">V3</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="kitType" class="required_field">Kit Type</label>
                                        <select class="form-control get-value" id="kitType" name="kitType" required>
                                            <option value="">Select</option>
                                            <option value="json">JSON</option>
                                            <option value="xml">XML</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="segment" class="required_field">Segment</label>
                                        <select class="form-control get-value" id="segment" name="segment" required>
                                            <option value="">Select</option>
                                            @foreach($segment as $data)
                                            <option value="{{$data['product_sub_type_id']}}">{{$data['product_sub_type_code']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status" class="required_field">Status</label>
                                        <select class="form-control get-value" id="status" name="status" required>
                                            <option value="">Select</option>
                                            <option value="Active">Active</option>
                                            <option value="InActive">InActive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="comment" class="required_field">Comment</label>
                                        <input type="text" class="form-control get-value-data commentData" id="comment" name="comment" placeholder="Add Comment">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary btn-place" id="save-btn-v1" type="submit">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

</section>
@endsection
@section('scripts')
 <script>
    const saveVersion = "{{ route('admin.ic-configuration.version.save-version-data') }}";
    const updateVersion = "{{ route('admin.ic-configuration.version.update-version-data') }}";
    const deleteVersion = "{{ route('admin.ic-configuration.version.delete-version-data') }}";
 </script>
<script src="{{asset('admin1/js/ic-config/version.js')}}"></script>
@endsection