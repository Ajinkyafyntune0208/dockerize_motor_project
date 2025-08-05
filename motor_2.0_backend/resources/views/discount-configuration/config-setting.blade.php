@extends('layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Discount Configuration')])
@section('content')
<style>
    .nav.nav-tabs .nav-link.active {
        background: #1F3BB3 !important;
        color: #fff !important;
    }
</style>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Discount Configuration</h4>
                    <div class="row">
                        <div class="col-md-6 col-lg-4">
                            @if (session('error'))
                            <div class="alert alert-danger mt-3 py-1">
                                {{ session('error') }}
                            </div>
                            @endif

                            @if (session('success'))
                            <div class="alert alert-success mt-3 py-1">
                                {{ session('success') }}
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            @include('discount-configuration.tabs')
                            <div class="tab-content" id="">
                                <div class="tab-pane fade show active" id="nav-config">
                                    <form method="post" name="config-setting-form" action="">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">Applicable to User : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4">
                                                <input type="text" name="userCriteria" id="" required class="form-control" value="{{old('userCriteria') ?? $userCriteria}}">
                                                <span style="font-size:0.7rem;" class="text-danger">(Note: user criteria should be seperated by comma)</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">Applicable to ICs : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3">
                                                <select name="applicableIcs[]" id="applicableIcs" required multiple data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                                    @foreach ($icList as $ic)
                                                    <option value="{{$ic->company_id}}" {{old('applicableIcs') ? (in_array($ic->company_id, old('applicableIcs')) ? 'selected' : '') : ($ic->is_selected == true ? 'selected' : '')}}>{{$ic->company_name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-12">
                                                <button class="btn btn-primary">Save</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const validateIcsRoute = '{{route('admin.discount-configurations.validate-ics')}}'
    var proceed = true;
    document.querySelector('[name="config-setting-form"]').addEventListener('submit', (e) => {
        if (!proceed) {
            e.preventDefault();
            let check = confirm (`You have unselected one or more insurance companies, which will also clear the IC wise configuration settings. Please confirm to proceed.`);
            if (check) {
                e.target.submit();
            }
        }
    })


    document.querySelector('#applicableIcs').addEventListener('change', (e) => {
        let formData = new FormData();
        formData.append('ic', $('#applicableIcs').val())
        validateIcs(validateIcsRoute, formData).then((res) => {
            if (res.response && res.response.status == true && res.response.is_confirm == true) {
                proceed = false
            } else {
                proceed = true
            }
        })
    })


    async function validateIcs(url, data)
    {
        const response = await fetch(url, {
            method: 'post',
            headers: {
            "Accept": "application/json",
            'X-CSRF-TOKEN': document.querySelector('[name="csrf-token"]').getAttribute('content')
            },
            body : data
        })
        response.response = await response.json();
        return response;
    }
</script>
@endsection
