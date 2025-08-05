
@extends('layout.app', ['activePage' => 'vahan', 'titlePage' => __('vahan')])
@section('content')
    <style>
    </style>
    <div class="content-wrapper">
        @if(request()->route()->getName() !== 'admin.vahan-service-credentials.index')
        <div class="card col-lg-12 grid-margin stretch-card pt-4">
            <div class="container col-lg-12 grid-margin">
                <form action="{{ route('admin.vahan_configurator') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col"><h5 class="card-title">Vahan Configurator Enabled</h5></div>
                    </div>
                    <div class="row">
                        <label class="col-md-6">1. Vahan Configurator Enabled</label>
                        <div class="col-md-2 form-check form-switch">
                            <input type="hidden" name="config[vahan_configurator_enabled][value]" value="N">
                            <input class="form-check-input" type="checkbox" role="switch" name="config[vahan_configurator_enabled][value]" value="Y" {{config('vahanConfiguratorEnabled') == 'Y' ? 'checked' : ''}}>
                            <input name="config[vahan_configurator_enabled][label]" type="hidden" value="Vahan Configurator Enabled" class="form-control">
                            <input name="config[vahan_configurator_enabled][key]" type="hidden" value="vahanConfiguratorEnabled" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <label class="col-md-6">2. Vahan Configurator Enabled on Proposal Page</label>
                        <div class="col-md-2 form-check form-switch">
                            <input type="hidden" name="config[vahan_configurator_enabled_on_proposal_page][value]" value="N">
                            <input class="form-check-input" type="checkbox" name="config[vahan_configurator_enabled_on_proposal_page][value]" value="Y" {{config('proposalPage.isVehicleValidation') == 'Y' ? 'checked' : ''}}>
                            <input name="config[vahan_configurator_enabled_on_proposal_page][label]" type="hidden" value="Vahan Configurator Enabled on Proposal Page" class="form-control">
                            <input name="config[vahan_configurator_enabled_on_proposal_page][key]" type="hidden" value="proposalPage.isVehicleValidation" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <label class="col-md-6">3. Vahan Configurator Enabled after pop-up deletion</label>
                        <div class="col-md-2 form-check form-switch">
                            <input type="hidden" name="config[vahan_configurator_enabled_after_pop-up_deletion][value]" value="N">
                            <input class="form-check-input" type="checkbox" name="config[vahan_configurator_enabled_after_pop-up_deletion][value]" value="Y"  {{config('proposalPage.vehicleValidation.enableIsCheckSectionMissmatched') == 'Y' ? 'checked' : ''}}>
                            <input name="config[vahan_configurator_enabled_after_pop-up_deletion][label]" type="hidden" value="Vahan Configurator Enabled after pop-up deletion" class="form-control">
                            <input name="config[vahan_configurator_enabled_after_pop-up_deletion][key]" type="hidden" value="proposalPage.vehicleValidation.enableIsCheckSectionMissmatched" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label>4. Add the expiry days for Vahan Export File</label>
                        </div>
                        <div class="col-md-2">
                            <input class="form-group" style="margin-left: -55px; width: 50px;" type="number" name="config[vahan_exported-file_Expire_in_days][value]" value="{{ (config('vahanExport.fileExpiry.days')) }}"  > 
                            <input name="config[vahan_exported-file_Expire_in_days][label]" type="hidden" value="vahan exported file Expire in days" class="form-control">
                            <input name="config[vahan_exported-file_Expire_in_days][key]" type="hidden" value="vahanExport.fileExpiry.days" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary btn-sm" type="submit" style="padding: 10px 10px; font-size: 12px;float: right;">Submit</button>
                        </div>
                    </div>
                    </form>
            </div>
        </div>
        @endif
    </div>
    @if(request()->route()->getName() === 'admin.vahan-service-credentials.index')
    <div class="content-wrapper" style="margin-top:-300px;">
    @else
    <div class="content-wrapper" style="margin-top:-40px;">
    @endif
        <div class="row justify-content-center">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        @if ($cred == 'Y')
                            <h5 class="card-title">Vahan Service Credentials</h5>
                        @else
                            <h5 class="card-title">Vahan Service
                                <a href="{{ route('admin.vahan_service.create') }}"
                                    class="btn btn-primary btn-sm float-end">Add
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
                                        <tr class="text-center">
                                            <th scope="col">Vahan service name</th>
                                            <th scope="col">Vahan service code</th>
                                            <th scope="col">Status</th>
                                            <th scope="col" class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-center">
                                        @foreach ($vahan_Services as $key => $vahan_Service)
                                            @if (!empty($vahan_Service))
                                            
                                                <tr>
                                                    <td scope="col">{{ $vahan_Service->vahan_service_name }}</td>
                                                    <td scope="col">{{ $vahan_Service->vahan_service_name_code }}</td>
                                                    <td scope="col" class="text-right">
                                                        <span
                                                            class="badge badge-{{ $vahan_Service->status == 'Active' ? 'success' : 'danger' }}">{{ $vahan_Service->status == 'Active' ? 'Active' : 'Inactive' }}</span>
                                                    </td>
                                                    @if ($cred == 'Y')
                                                        <td scope="col">
                                                                    <div class="text-center">
                                                                    @if($vahan_Service->status == 'Active')
                                                                    
                                                            <a class="btn btn-primary btn-sm"
                                                                href="{{ route('admin.vahan_credentials_read.crud', $vahan_Service) }}",
                                                                title=""><i class="fa fa-plus-circle"
                                                                    aria-hidden="true"></i></a>
                                                                    @else
                                                                    
                                                                    <span>NA</span>
                                                                   
                                                                    @endif
                                                                </div>
                                                        </td>
                                                    @else
                                                        <td scope="col">
                                                            <a class="btn btn-primary btn-sm"
                                                                href="{{ route('admin.vahan_service.edit', $vahan_Service) }}"
                                                                title="Edit"><i class="fa fa-pencil-square-o"
                                                                    aria-hidden="true"></i></a>
                                                            <form method="post"
                                                                action="{{ route('admin.vahan_credentials.delete', ['id' => $vahan_Service, 'cred' => $cred]) }}"
                                                                accept-charset="UTF-8" style="display:inline">
                                                                {{ method_field('DELETE') }}
                                                                {{ csrf_field() }}
                                                                <button type="submit" class="btn btn-danger btn-sm"
                                                                    title="Delete Product"
                                                                    onclick="return confirm(&quot;Confirm delete?&quot;)"><i
                                                                        class="fa fa-trash-o"
                                                                        aria-hidden="true"></i></button>
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
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('#vahan_service_table').DataTable();
            $('[name="vahan_service_table_length"]').attr({
            "data-style":"btn-sm btn-primary",
            "data-actions-box":"true",
            "class":"selectpicker px-3",
            "data-live-search":"true"
        });
        $('.selectpicker').selectpicker();
        });
    </script>

@endpush
