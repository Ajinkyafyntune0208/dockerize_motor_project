@extends('admin_lte.layout.app', ['activePage' => 'Manufacturer-Priority-List', 'titlePage' => __('Manufacturer-Priority-List')])
@section('content')
<style>
    .btn-outline-primary {
        margin-bottom: 6px;
    }

    .cv-type-container {
        display: none;
    }
</style>

@if (!empty($company))
<div class="card card-primary">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <label>Select Business Type</label>
            </div>
            <div class="col-md-4">
                <select class="form-control businessTypeSelect" id="businessTypeSelect" required>
                    <option value="B2B">B2B</option>
                    <option value="B2C">B2C</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <label>Vehicle Type</label>
                <select class="form-control" id="vehicleTypeSelect" required>
                    <option value="car">Car</option>
                    <option value="bike">Bike</option>
                    <option value="cv">Commercial Vehicle</option>
                </select>
            </div>
            <div class="col-md-4 cv-type-container" id="cvTypeContainer">
                <label>CV Sub-Type</label>
                <select class="form-control" id="cvTypeSelect">
                    <option value="">Nothing Selected</option>
                    <option value="gcv">GCV</option>
                    <option value="pcv">PCV</option>
                </select>
            </div>
            <div class="col-md-4 cv-type-container" id="GCVTypeContainer">
                <label>GCV|PCV Sub-Type</label>
                <select class="form-control GCVSelect" id="gcvTypeSelect">
                    <option value="">Nothing Selected</option>
                </select>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('admin.manufacturer-priority.store') }}" method="POST" id="b2bInsurerForm">
    @csrf
    <input type="hidden" name="businessType" id="BusinessType">
    <input type="hidden" name="vehicle_type" id="b2bVehicleType">
    <input type="hidden" name="gcvSubType" id="gcvSubType">
    <input type="hidden" name="cv_type" id="b2bCvType">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th class="text-center">Priority</th>
                            <th class="text-center">Manufacturer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 1; $i <= 12; $i++)
                            @php
                            $selectedInsurer=isset($Exixtdata[$i - 1]) ? $Exixtdata[$i - 1]->insurer : '';
                            @endphp
                            <tr>
                                <td class="text-center">{{ $i }}</td>
                                <td class="text-center">
                                    <select class="form-control insurer-select-b2c" name="insurers[]" required>
                                        <option value="">Select Manufacturer</option>
                                        @foreach ($company as $key => $value)
                                        <option value="{{ $value }}" {{ $value == $selectedInsurer ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endfor
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-center pb-3">
            <button type="submit" class="btn btn-success btn-sm saveBtn">Save</button>
            <button class="btn btn-danger btn-sm resetBtn" data-form="#b2bInsurerForm">Reset</button>
        </div>
    </div>
</form>
@endif
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('.resetBtn').click(function() {
            let formId = $(this).data('form');
            $(formId).find('select').each(function() {
                $(this).val('');
            });
        });
        const $businessType = $('.businessTypeSelect');
        const $vehicleType = $('#vehicleTypeSelect');
        const $cvType = $('#cvTypeSelect');
        const $gcvType = $('#gcvTypeSelect');
        const $cvContainer = $('#cvTypeContainer');
        const $GCVTypeContainer = $('#GCVTypeContainer');
        const $vehicleTypeHidden = $('#b2bVehicleType');
        const $gcvTypeHidden = $('#gcvSubType');
        const $cvTypeHidden = $('#b2bCvType');
        let businessType = $('#businessTypeSelect').val();
        $('#BusinessType').val(businessType);
        fetchAndAutofill();

        function toggleCV() {
            if ($vehicleType.val() === 'cv') {
                $cvContainer.show();
            } else {
                $cvContainer.hide();
                $cvType.val('');
                $GCVTypeContainer.hide();
                $gcvType.val('');
            }

            if ($cvType.val() === 'gcv' || $cvType.val() === 'pcv') {
                if ($cvType.val() === 'gcv') {
                    $('.GCVSelect').empty();
                    $('.GCVSelect').append('<option value="">Nothing selected</option>');
                    $('.GCVSelect').append('<option value="PICK UP/DELIVERY/REFRIGERATED VAN">PICK UP/DELIVERY/REFRIGERATED VAN</option>');
                    $('.GCVSelect').append('<option value="DUMPER/TIPPER">DUMPER/TIPPER</option>');
                    $('.GCVSelect').append('<option value="TRUCK">TRUCK</option>');
                    $('.GCVSelect').append('<option value="TRACTOR">TRACTOR</option>');

                } else if ($cvType.val() === 'pcv') {
                    $('.GCVSelect').empty();
                    $('.GCVSelect').append('<option value="">Nothing selected</option>');
                    $('.GCVSelect').append('<option value="AUTO-RICKSHAW">AUTO-RICKSHAW</option>');
                    $('.GCVSelect').append('<option value="TAXI">TAXI</option>');
                    $('.GCVSelect').append('<option value="ELECTRIC-RICKSHAW">E-RICKSHAW</option>');
                }
                $GCVTypeContainer.show();
            } else {
                $GCVTypeContainer.hide();
                $gcvType.val('');
            }
        }


        function fillHiddenInputs() {
            $vehicleTypeHidden.val($vehicleType.val());
            $cvTypeHidden.val($cvType.val());
            $gcvTypeHidden.val($gcvType.val());
        }

        updateDropdowns();

        function showCVsubtypes() {
            if ($cvType.val() === 'GCV' || $cvType.val() === 'PCV') {
                $GCVTypeContainer.show();
            } else {
                $GCVTypeContainer.hide();
                $gcvType.val('');
            }
        }

        function fetchAndAutofill() {
            let businessType = $businessType.val();
            let vehicleType = $vehicleType.val();
            let cvType = $cvType.val();
            let gcvSubType = $gcvType.val();

            if (!businessType || !vehicleType || (vehicleType === 'cv' && (!cvType || !gcvSubType))) {
                $('.insurer-select-b2c').val('');
                return;
            }

            $.ajax({
                url: 'manufacturer-priorityfetchinsurers',
                method: 'GET',
                data: {
                    business_type: businessType,
                    vehicle_type: vehicleType,
                    cv_type: cvType,
                    gcvSubType: gcvSubType
                },
                success: function(res) {
                    let manufacturer = Object.values(res.menufacturer);
                    if (manufacturer) {
                        $('.insurer-select-b2c').empty();
                        $('.insurer-select-b2c').append('<option value="">Select Manufacturer </option>');
                        $(manufacturer).each(function(index) {
                            $('.insurer-select-b2c').append('<option value="' + manufacturer[index] + '">' + manufacturer[index] + '</option>');
                        });
                    }
                    if (res && res.data && Array.isArray(res.data)) {
                        $('.insurer-select-b2c').each(function(index) {
                            $(this).val(res.data[index] ?? '');
                        });
                        updateDropdowns();
                    } else {
                        $('.insurer-select-b2c').val('');
                        updateDropdowns();
                    }
                },
                error: function() {
                    $('.insurer-select-b2c').val('');
                }
            });
        }

        $businessType.on('change', function() {
            let businessType = $('#businessTypeSelect').val();
            $('#BusinessType').val(businessType);

            fillHiddenInputs();
            toggleCV();
            fetchAndAutofill();
        });

        $vehicleType.on('change', function() {
            fillHiddenInputs();
            toggleCV();
            fetchAndAutofill();
        });

        $cvType.on('change', function() {
            fillHiddenInputs();
            toggleCV();
        });
        $gcvType.on('change', function() {
            fillHiddenInputs();
            fetchAndAutofill();
        });



        // Initial call in case prefilled
        toggleCV();
        fillHiddenInputs();
    });

    function updateDropdowns() {
        $('.insurer-select-b2c').each(function() {
            let selected = [];
            $('.insurer-select-b2c').each(function() {
                let val = $(this).val();
                if (val && val !== 'none') {
                    selected.push(val);
                }
            });

            let current = $(this).val();
            $(this).find('option').each(function() {
                if ($(this).val() === current || $(this).val() === '') {
                    $(this).show();
                } else if (selected.includes($(this).val())) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
        });
    }
    $('.insurer-select-b2c').change(function() {
        updateDropdowns();
    });
</script>
@endsection('scripts')