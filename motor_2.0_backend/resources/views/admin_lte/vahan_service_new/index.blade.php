
@extends('admin_lte.layout.app', ['activePage' => 'vahan', 'titlePage' => __(($cred != 'Y') ? 'Vahan Service' : 'Vahan Service Credentials')])
@section('content')
@if(request()->route()->getName() !== 'admin.vahan-service-credentials.index')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.vahan_configurator') }}" method="POST">
            @csrf
            <div class="row">
                <label class="col-md-6">1. Vahan Configurator Enabled</label>
                <div class="col-md-2 custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input type="hidden" name="config[vahan_configurator_enabled][value]" value="N">
                    <input type="checkbox" name="config[vahan_configurator_enabled][value]" class="custom-control-input" id="customSwitch3" value="Y" {{config('vahanConfiguratorEnabled') == 'Y' ? 'checked' : ''}}>
                    <label class="custom-control-label" for="customSwitch3"></label>
                    <input name="config[vahan_configurator_enabled][label]" type="hidden" value="Vahan Configurator Enabled" class="form-control">
                    <input name="config[vahan_configurator_enabled][key]" type="hidden" value="vahanConfiguratorEnabled" class="form-control">
                </div>
            </div>
            <div class="row">
                <label class="col-md-6">2. Vahan Configurator Enabled on Proposal Page</label>
                <div class="col-md-2 custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input type="hidden" name="config[vahan_configurator_enabled_on_proposal_page][value]" value="N">
                    <input type="checkbox" name="config[vahan_configurator_enabled_on_proposal_page][value]" class="custom-control-input" id="customSwitch4" value="Y" {{config('proposalPage.isVehicleValidation') == 'Y' ? 'checked' : ''}}>
                    <label class="custom-control-label" for="customSwitch4"></label>
                    <input name="config[vahan_configurator_enabled_on_proposal_page][label]" type="hidden" value="Vahan Configurator Enabled on Proposal Page" class="form-control">
                    <input name="config[vahan_configurator_enabled_on_proposal_page][key]" type="hidden" value="proposalPage.isVehicleValidation" class="form-control">
                </div>
            </div>
            <div class="row">
                <label class="col-md-6">3. Vahan Configurator Enabled after pop-up deletion</label>
                <div class="col-md-2 custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input type="hidden" name="config[vahan_configurator_enabled_after_pop-up_deletion][value]" value="N">
                    <input type="checkbox" name="config[vahan_configurator_enabled_after_pop-up_deletion][value]" class="custom-control-input" id="customSwitch5" value="Y" {{config('proposalPage.vehicleValidation.enableIsCheckSectionMissmatched') == 'Y' ? 'checked' : ''}}>
                    <label class="custom-control-label" for="customSwitch5"></label>
                    <input name="config[vahan_configurator_enabled_after_pop-up_deletion][label]" type="hidden" value="Vahan Configurator Enabled after pop-up deletion" class="form-control">
                    <input name="config[vahan_configurator_enabled_after_pop-up_deletion][key]" type="hidden" value="proposalPage.vehicleValidation.enableIsCheckSectionMissmatched" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label>4. Add the expiry days for Vahan Export File</label>
                </div>
                <div class="col-md-2">
                    <input class="form-group" style="margin-left: -10px; width: 50px;" type="number" name="config[vahan_exported-file_Expire_in_days][value]" value="{{ (config('vahanExport.fileExpiry.days')) }}"  >
                    <input name="config[vahan_exported-file_Expire_in_days][label]" type="hidden" value="vahan exported file Expire in days" class="form-control">
                    <input name="config[vahan_exported-file_Expire_in_days][key]" type="hidden" value="vahanExport.fileExpiry.days" class="form-control">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit" style="float: right;">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<div class="card">
    @if ($cred != 'Y')
    <div class="card-header">
        <div class="card-tools">
            @can('vahan_service.create')
            <a href="{{ route('admin.vahan_service.create') }}" class="btn btn-primary">Add New Service</i></a>
            @endcan
        </div>
    </div>
    @endif
    <div class="card-body">
        @if (!empty($vahan_Services))
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Vahan service name</th>
                    <th>Vahan service code</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vahan_Services as $key => $vahan_Service)
                    @if (!empty($vahan_Service))

                        <tr>
                            @if ($cred == 'Y')
                                <td>
                                    <div class="text-center">
                                    @if($vahan_Service->status == 'Active')
                                        <a class="btn btn-info" href="{{ route('admin.vahan_credentials_read.crud', $vahan_Service) }}" title=""><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
                                    @else
                                        <span>NA</span>
                                    @endif
                                    </div>
                                </td>
                            @else
                                <td>
                                    @can('vahan_service.edit')
                                    <a class="btn btn-info" href="{{ route('admin.vahan_service.edit', $vahan_Service) }}" title="Edit"><i class="fa fa-edit" aria-hidden="true"></i></a>
                                    @endcan
                                    @can('vahan_service.delete')
                                    <form method="post" action="{{ route('admin.vahan_credentials.delete', ['id' => $vahan_Service, 'cred' => $cred]) }}" accept-charset="UTF-8" style="display:inline">
                                        {{ method_field('DELETE') }}
                                        {{ csrf_field() }}
                                        <button type="submit" class="btn btn-danger" title="Delete Product" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                    @endcan
                                </td>
                            @endif
                            <td>{{ $vahan_Service->vahan_service_name }}</td>
                            <td>{{ $vahan_Service->vahan_service_name_code }}</td>
                            <td>
                                <button disabled class="btn btn-{{ $vahan_Service->status == 'Active' ? 'success' : 'danger' }}">{{ $vahan_Service->status == 'Active' ? 'Active' : 'Inactive' }}</button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            if($("#data-table").length){
                $(function () {
                    $("#data-table").DataTable({
                        "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
                    "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                        extend: 'colvis',
                        columns: 'th:not(:nth-child(2))'
                    }]
                    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
                });
            }
        });
    </script>

@endsection
