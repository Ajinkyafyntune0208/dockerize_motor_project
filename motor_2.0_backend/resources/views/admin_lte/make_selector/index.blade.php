@extends('admin_lte.layout.app', ['activePage' => 'make_selector', 'titlePage' => __('Manufacturer selector')])
@section('content')
<div class="content">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        {{-- Search Form --}}
                        <form action="{{ route('admin.make_selector') }}" method="post" id="mmvBlocker">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group w-100">
                                        <label class="required">Journey Type</label>
                                        <select name="sellerType" id="sellerType" class="form-control selectpicker" data-live-search="true" required>
                                            <option value="">Nothing selected</option>
                                            <option value="B2B" {{ old('sellerType', request()->input('sellerType')) == 'B2B' ? 'selected' : '' }}>B2B</option>
                                            @if (config('constants.motorConstant.IS_USER_ENABLED') == 'Y')
                                            <option value="B2C" {{ old('sellerType', request()->input('sellerType')) == 'B2C' ? 'selected' : '' }}>B2C</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group w-100">
                                        <label class="required">Segment</label>
                                        <select name="segment" id="segment" class="form-control selectpicker" data-live-search="true" required>
                                            <option value="">Nothing selected</option>
                                            @foreach ($vehicleCats as $subType)
                                                <option value="{{ $subType['productSubTypeCode'] }}" 
                                                    {{ old('segment', request()->segment ?? '') == $subType['productSubTypeCode'] ? 'selected' : '' }}>
                                                    {{ $subType['productSubTypeCode'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                 {{-- Add hidden input for product_sub_type_id --}}
                                @foreach ($vehicleCats as $subType)
                                <input type="hidden" id="hiddenVehicleCat_{{ $loop->index }}" 
                                       value="{{ $subType['productSubTypeId'] }}" 
                                       data-product-type="{{ $subType['productSubTypeCode'] }}">
                                @endforeach
                                @if (auth()->user()->can('make_selector.list'))
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-info btn-md" id="search-button" style="float: right;" >Search</button>
                                </div>
                                @endif
                            </div>
                        </form>

                        {{-- Results and Submit Form --}}
                        <form action="{{ route('admin.submit-form') }}" method="post" id="submitForm" style="{{ !empty($data) ? 'display: block;' : 'display: none;' }}">
                            @csrf
                            <input type="hidden" name="sellerType" id="hiddenSellerType">
                            <input type="hidden" name="segment" id="hiddenSegment">
                            <input type="hidden" name="productSubTypeId" id="hiddenProductSubTypeId">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <label>Select Manufacturers:</label>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                                        @foreach ($data as $item)
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="manf_names[]" 
                                                       value="{{ $item['manf_name'] }}" id="manf_{{ $loop->index }}"
                                                       {{ in_array($item['manf_name'], $preselectedManufacturers) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="manf_{{ $loop->index }}">{{ $item['manf_name'] }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if (auth()->user()->can('make_selector.show'))
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-success btn-md" style="float: right;" id="submitdata">Submit</button>
                                </div>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
   document.getElementById('search-button').addEventListener('click', function (e) {
    e.preventDefault(); // Prevent default form submission

    // Get values from the mmvBlocker form
    const sellerType = document.getElementById('sellerType').value;
    const segment = document.getElementById('segment').value;

    // Check if required values are present
    if (!sellerType || !segment) {
        alert('Please select both Journey Type and Segment.');
        return;
    }

    // Update hidden inputs in submitForm
    document.getElementById('hiddenSellerType').value = sellerType;
    document.getElementById('hiddenSegment').value = segment;

    // Submit the mmvBlocker form to fetch results
    document.getElementById('mmvBlocker').submit();
});

document.getElementById('submitdata').addEventListener('click', function (e) {
    e.preventDefault(); // Prevent default form submission

    // Ensure hidden inputs are updated with the latest values
    const sellerType = document.getElementById('sellerType').value;
    const segment = document.getElementById('segment').value;

    document.getElementById('hiddenSellerType').value = sellerType;
    document.getElementById('hiddenSegment').value = segment;

    console.log('Seller Type:', sellerType);
    console.log('Segment:', segment);

    // Find the product_sub_type_id corresponding to the selected segment
    const hiddenInputs = document.querySelectorAll('[id^="hiddenVehicleCat_"]');
       let selectedProductSubTypeId = '';
       hiddenInputs.forEach((input) => {
           if (input.dataset.productType === segment) {
               selectedProductSubTypeId = input.value;
           }
       });
    // Update the hidden input for product_sub_type_id in the submitForm
    document.getElementById('hiddenProductSubTypeId').value = selectedProductSubTypeId;
    // Check for missing data in hidden inputs
    if (!sellerType || !segment) {
        alert('Seller Type or Segment is missing. Please complete the form.');
        return;
    }
    if(!selectedProductSubTypeId){
        alert('For the given segment, product_sub_type_id is missing in the database.');
        return;
    }

    // Submit the submitForm
    alert('Manufacturers edited');
    document.getElementById('submitForm').submit();
});
</script>

@endsection
