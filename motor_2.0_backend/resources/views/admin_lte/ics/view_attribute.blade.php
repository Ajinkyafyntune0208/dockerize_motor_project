@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('View Attributes')])

@section('content')
<!-- <div class="container"> -->
    <form id="viewAttrfilterForm">
        <div class="row mb-1">
            <div class="col-md-3">
                <div class="form-group mb-1">
                    <label for="selectIC" class="required_field">IC Integration</label>
                    <select class="form-control wide-dropdown" id="selectIC" name="selectIC">
                        <option value="">Select IC Integration</option>
                        @foreach($alias as $data)
                        <option value="{{ $data->ic_alias }}">{{ $data->ic_alias }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-1">
                    <label for="integrationType" class="required_field">Integration Type</label>
                    <select class="form-control wide-dropdown" id="integrationType" name="integrationType" disabled>
                        <option value="">Select Integration Type</option>
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-1">
                    <label for="segment" class="required_field">Segment</label>
                    <select class="form-control wide-dropdown" id="segment" name="segment" disabled>
                        <option value="">Select Segment</option>
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-1">
                    <label for="businessType" class="required_field">Business Type</label>
                    <select class="form-control wide-dropdown" id="businessType" name="businessType" disabled>
                        <option value="">Select Business Type</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row mb-5">
            <div class="col-md-10"></div>
            <div class="col-md-2 my-1">
                <button class="btn btn-primary float-right" id="save-btn" type="button" style="width:150px;align-items: align-right;">Search</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered" id="attributesTable" style="display: none; width: 100%;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Attribute Name</th>
                    <th style="width:20%">Attribute Trail</th>
                    <th>Sample Value</th>
                    <th>Sample Type</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
<!-- </div> -->
@endsection

@section('scripts')
<script>
    var viewAttributeRoute = "{{ route('admin.ic-configuration.view_attribute') }}";
</script>
<script src="{{ asset('admin1/js/ic-config/view_attributes.js') }}"></script>
@endsection
@section('styles')
<style>
    .table-responsive {
        overflow-x: auto;
        max-width: 100%;
    }

    .wide-dropdown {
        width: 100%;
    }

    .tooltip-wrapper {
        display: inline-block;
        cursor: pointer;
    }

    .tooltip-wrapper .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 32.5rem;
        min-width: 10rem;
        display: inline-block;
    }

    #attributesTable thead input {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
    }

    .dataTables_filter {
        margin-bottom: 10px;
    }
</style>
@endsection