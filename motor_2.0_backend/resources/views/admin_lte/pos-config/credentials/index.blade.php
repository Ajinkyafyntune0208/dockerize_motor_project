@extends('admin_lte.layout.app', ['activePage' => 'Pos Credential Configurator', 'titlePage' => __('Pos Credential Configurator')])
@section('content')

<!-- <div class="content-wrapper"> -->
    <div class="grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.pos-config.home')}}" method="POST">
                    @csrf
                    <!-- <div class="row">
                        <div class="col-sm-9" style="margin-top: 14px;">
                            <h4 class="card-title">Pos Credential Configurator</h4>
                        </div>
                    </div> -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <strong><label for="pos" class = "required">Pos</label></strong>
                                <select name="pos[]" id="pos" class="selectpicker w-100 " data-live-search="true"
                                    data-style="btn-sm btn-success" multiple data-actions-box="true" required>
                                    @foreach ($posList as $key => $pos)
                                        <option value="{{ $key }}">{{ $pos }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
    
                        <div class="col-md-3">
                            <div class="form-group">
                                <strong><label for="section" class = "required">Sections</label></strong>
                                <select name="section[]" id="section" class="selectpicker w-100 " data-live-search="true"
                                    data-style="btn-sm btn-success" multiple data-actions-box="true" required>
                                    @foreach ($sections as $sec)
                                        <option value="{{ $sec['product_sub_type_id'] }}">{{ $sec['product_sub_type_code'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
    
    
                        <div class="col-md-3">
                            <div class="form-group">
                                <strong><label for="insuranceCompany" class = "required">Insurance Company</label></strong>
                                <select name="insuranceCompany[]" id="insuranceCompany" class="selectpicker w-100 " data-live-search="true"
                                    data-style="btn-sm btn-success" data-actions-box="true" required>
                                    <option value="" selected disabled>Select Insurance Company</option>
                                    @foreach ($ics as $ic)
                                        <option value="{{ $ic['company_id'] }}">{{ $ic['company_alias'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
    
                    <div class="row fields-section mt-5">
                        <div class="col-md-6 col-lg-4 justify-content-center">
                            @if (session('success'))
                                <div class="alert alert-success py-1">
                                    {{ session('success') }}
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger py-1">
                                    {{ session('error') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row pos-config-detail">
                        
                    </div>
                </form>
            </div>
        </div>
    </div>
<!-- </div> -->
<div class="d-none">
    <form action="{{route('admin.pos-config.destroy')}}" method="post" class="deleteForm">
        @method('DELETE')
        @csrf
        <input type="hidden" name="deleteId" value="">
    </form>
</div>
<script>
    const credsFields = '<?php echo $fields; ?>';
    const fetchCredsUrl = '<?php echo route("pos-imd-config.fetch") ?>';
</script>
<script src="{{asset('admin1/js/pos-imd-config.js')}}"></script>
@endsection