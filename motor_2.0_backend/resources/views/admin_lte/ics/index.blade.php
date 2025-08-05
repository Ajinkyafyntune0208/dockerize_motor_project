@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Premium Calculation Configurator')])

@section('content')
@if (auth()->user()->can('premium_calculation_configurator.import'))
    <div class="row justify-content-end align-items-end mb-1">
        <div class="col-md-6 col-lg-3 text-right">
            <button class="btn btn-sm btn-primary import-btn" data-toggle="modal" data-target="#importModal"> Import</button>
        </div>
    </div>
@endif
<!-- Tabs for Active and Inactive Configurations -->
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Active Integration</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="inactive-tab" data-toggle="tab" href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Inactive Integration</a>
    </li>
</ul>

<div class="tab-content mt-3">
    <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
        <table id="active-data-table" class="table">
            <thead>
                <tr>
                    <th>Sr.No</th>
                    <th>IC Integration Type</th>
                    <th>Segment</th>
                    <th>Business Type</th>
                    <th>Configuration Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($activeIcs as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->ic_alias }} - {{ $item->integration_type }}</td>
                    <td>{{ $item->segment }}</td>
                    <td>{{ $item->business_type }}</td>
                    <td>

                        @if ($item->pca_active)
                        <span class="badge badge-success">Enabled</span>
                        @else
                        <span class="badge badge-danger">Disabled</span>
                        @endif
                    </td>
                    <td>
            @if (auth()->user()->can('premium_calculation_configurator.show'))
            <button  type="button" class="btn btn-sm btn-outline-primary view-ic" data-id="{{ ($item->slug) }}" data-ic-alias="{{ $item->ic_alias }}" data-integration-type="{{ $item->integration_type }}" data-segment="{{ $item->segment }}" data-business-type="{{ $item->business_type }}" onclick="viewFormula(this)" style="padding-left: 6px; padding-right: 10px;"><i class="fa fa-eye view-ic"></i></button>
                @endif
                @if (auth()->user()->can('premium_calculation_configurator.edit'))
                <a href="{{ route('admin.ic-configuration.premium-calculation-configurator.edit', base64_encode($item->slug)) }}"  style="padding-left: 6px; padding-right: 10px" class="btn btn-sm btn btn-sm btn-success fas fa fa-edit"></a>
                <button type="button" class="btn btn-sm btn-outline-primary fas fa-clone clone-ic" data-id="{{ base64_encode($item->slug) }}"></button>
                @endif
                @if (auth()->user()->can('premium_calculation_configurator.export'))
                <a href="{{ route('admin.ic-configuration.export-config', base64_encode($item->slug)) }}" class="btn btn-sm btn-warning fas fa-file-pdf text-white">
                    Export
                </a>
                @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
        <table id="inactive-data-table" class="table">
            <thead>
                <tr>
                    <th>Sr.No</th>
                    <th>IC Integration Type</th>
                    <th>Segment</th>
                    <th>Business Type</th>
                    <th>Configuration Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inactiveIcs as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->ic_alias }} - {{ $item->integration_type }}</td>
                    <td>{{ $item->segment }}</td>
                    <td>{{ $item->business_type }}</td>

                    <td>
                        @if ($item->pca_active)
                        <span class="badge badge-success">Enabled</span>
                        @else
                        <span class="badge badge-danger">Disabled</span>
                        @endif
                    </td>
                    <td>
            @if (auth()->user()->can('premium_calculation_configurator.show'))
            <button type="button" class="btn btn-sm btn-outline-primary view-ic" data-id="{{ ($item->slug ) }}" data-ic-alias="{{ $item->ic_alias }}" data-integration-type="{{ $item->integration_type }}" data-segment="{{ $item->segment }}" data-business-type="{{ $item->business_type }}" onclick="viewFormula(this)" style="padding-left: 6px; padding-right: 10px;"><i class="fa fa-eye view-ic"></i></button>
                @endif
                @if (auth()->user()->can('premium_calculation_configurator.edit'))
                <a href="{{ route('admin.ic-configuration.premium-calculation-configurator.edit', base64_encode($item->slug)) }}"  style="padding-left: 6px; padding-right: 10px" class="btn btn-sm btn btn-sm btn-success fas fa fa-edit"></a>
                <button type="button" class="btn btn-sm btn-outline-primary fas fa-clone clone-ic" data-id="{{ base64_encode($item->slug) }}"></button>
                @endif
                @if (auth()->user()->can('premium_calculation_configurator.export'))
                <a href="{{ route('admin.ic-configuration.export-config', base64_encode($item->slug)) }}" class="btn btn-sm btn-warning fas fa-file-pdf text-white">
                    Export
                </a>
                @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="icModal" tabindex="-1" role="dialog" aria-labelledby="icModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="icModalLabel">IC Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="accordionIC">
                    <!-- Accordion content will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="modal-close">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- /.modal -->

<!-- Clone Modal -->
<div class="modal fade" id="icCloneModal" tabindex="-1" role="dialog" aria-labelledby="cloneModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cloneModalLabel">Clone IC Configuration to</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="cloneICForm">
                    @csrf
                    <div class="form-group row">
                        <label for="combinedICIntegration" class="col-sm-4 col-form-label">IC Integration</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="combinedICIntegration" name="combinedICIntegration">
                                <option value="" disabled selected>Select IC Integration</option>
                                @foreach($alias->unique('ic_alias') as $data)
                                    <option value="{{ $data->ic_alias }}">{{ $data->ic_alias }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="integrationType" class="col-sm-4 col-form-label">Integration Type</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="integrationType" name="integrationType" disabled>
                                <option value="" disabled selected>Select Integration Type</option>
                                @foreach($alias as $data)
                                    <option value="{{ $data->integration_type }}" data-ic-alias="{{ $data->ic_alias }}">
                                        {{ $data->integration_type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="segment" class="col-sm-4 col-form-label">Segment</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="segment" name="segment" disabled>
                                <option value="" disabled selected>Select Segment</option>
                                @foreach($alias as $data)
                                    <option value="{{ $data->segment }}" data-ic-alias="{{ $data->ic_alias }}" data-integration-type="{{ $data->integration_type }}">
                                        {{ $data->segment }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="businessType" class="col-sm-4 col-form-label">Business Type</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="businessType" name="businessType" disabled>
                                <option value="" disabled selected>Select Business Type</option>
                                @foreach($alias as $data)
                                    <option value="{{ $data->business_type }}" data-ic-alias="{{ $data->ic_alias }}" data-integration-type="{{ $data->integration_type }}" data-segment="{{ $data->segment }}">
                                        {{ $data->business_type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="overrideexists" class="col-sm-4 col-form-label required_field">Override Exists</label>
                        <div class="col-sm-8">
                            <select class="form-control wide-dropdown" id="overrideexists" name="overrideexists">
                                <option value="" disabled selected>Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="$('#icCloneModal').modal('hide')">Cancel</button>
                <button type="button" class="btn btn-primary" id="cloneICButton">Clone</button>
            </div>
        </div>
    </div>
</div>


{{-- This modal is for export --}}
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Premium Calculation Configuration</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form class="import-form"  enctype="multipart/form-data">
            <div class="form-group">
              <label for="jsonFile" class="col-form-label">Json File:</label>
              <input type="file" name="jsonFile" class="form-control" id="jsonFile" accept=".json" required>
            </div>
            <div class="form-group">
                <label for="override" class="col-form-label">Override Existing Data:</label><br>
                Config Settings : <input type="checkbox" name="override[]" value="config" id="override"><br>
                Label Data : <input type="checkbox" name="override[]" value="label" id="override"><br>
                Bucket Data : <input type="checkbox" name="override[]" value="bucket" id="override">
                
              </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Import</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              </div>
          </form>
        </div>
      </div>
    </div>
  </div>


<style>
    .accordion .card {
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .accordion .card-header {
        font-size: 1.25rem;
        background-color: #f7f7f7;
        border-bottom: 1px solid #dee2e6;
        cursor: pointer;
    }

    .accordion .card-header:hover {
        background-color: #e9ecef;
    }

    .accordion .card-body {
        padding: 1.5rem;
        background-color: #fff;
    }

    .table {
        width: 100%;
        margin-bottom: 1rem;
        border-collapse: collapse;
    }

    .table-bordered {
        border: 1px solid #dee2e6;
    }

    .table-bordered th,
    .table-bordered td {
        border: 1px solid #dee2e6;
        padding: 0.75rem;
        vertical-align: top;
        text-align: left;
    }

    .table-bordered th {
        background-color: #f2f2f2;
        font-weight: bold;
    }

    .table-bordered tbody tr:hover {
        background-color: #f5f5f5;
    }

    .text-ellipses {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 25rem;
        display: inline-block;
    }

    #cloneDropdown {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: white;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        width: 600px;
        z-index: 1050;
        padding: 1rem;
    }

    .dropdown-header {
        display: flex;
        justify-content: space-between;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .dropdown-title {
        font-size: 1.25rem;
        font-weight: bold;
    }

    .close-dropdown {
        background: none;
        border: none;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
    }

    .dropdown-body {
        padding: 1rem;
    }

    .dropdown-body .form-group {
        margin-bottom: 1rem;
    }

    .dropdown-body .form-group:last-child::after {
        display: none;
    }

    .wide-dropdown {
        width: 100%;
    }

  .button-container {
        display: flex;
        justify-content: flex-end;
    }
    .button {
        /* background-color: #04AA6D; */
        border: none;
        color: white;
        padding: 8px 16px;
        text-align: center;
        text-decoration: none;
        font-size: 14px;
        margin: 4px 2px;
        cursor: pointer;
    }
</style>
@endsection

@section('scripts')
<script>
    const cloneUrl = "{{route('admin.ic-configuration.cloneIC')}}"
    const importUrl = "{{route('admin.ic-configuration.import-config')}}";
    var appUrl = "{{ config('app.url') }}";
</script>
<script src="{{ asset('admin1/js/ic-config/premium-configurator.js') }}"></script>
@endsection