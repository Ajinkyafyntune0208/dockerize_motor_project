@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Premium Calculation Configurator')])

{{-- premiumcalculationconfigurator css --}}
    <link rel="stylesheet" href="{{asset('css/premiumcalculationconfigurator.css')}}">

@section('content')
<div class="container mt-2">
    <form id="configForm" action="{{ route('admin.ic-configuration.premium-calculation-configurator.update', $ic) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="box">
                    <p><strong>IC Integration Type - </strong><span>{{ $icConfigurator->ic_alias }} - {{
                            $icConfigurator->integration_type }}</span></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <p><strong> SEGMENT - </strong><span>{{ $icConfigurator->segment }}</span></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <p><strong> BUSINESS TYPE - </strong><span>{{ $icConfigurator->business_type }}</span></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group box" style="display: flex;flex-direction: row;justify">
                    <div class="mr-1">
                        <label for="activation" class="m-0">Status : </label>
                    </div>
                    <div class="w-50">
                        <select name="activation" class="form-control form-control-sm pt-0 pb-0" style="max-height: 1.55rem">
                            <option value="DISABLE" {{$icConfigurator?->activation?->is_active ? '' : 'selected'}}>Disable</option>
                            <option value="ENABLE" {{$icConfigurator?->activation?->is_active ? 'selected' : ''}}>Enable</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion" id="accordionExample">
            @foreach($labels as $groupLabel => $keys)
            <div class="accordion-item">
                <div class="accordion-header" id="heading{{ $loop->index }}">
                    <div class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapse{{ $loop->index }}" aria-expanded="false" aria-controls="collapse{{ $loop->index }}">
                        {{ $groupLabel }}
                    </div>
                </div>
                <div id="collapse{{ $loop->index }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $loop->index }}" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                    @foreach($keys as $key)
                                @php
                                $attributeExists = $attributes->contains('label_key', $key->label_key);
                                @endphp
                                <div class="form-group row mb-3">
                                    <label for="{{ $key->label_key }}" class="col-sm-4 col-form-label dropdown-label">{{ $key->label_name }}</label>
                                    <div class="col-sm-4">
                                        <select name="{{ $key->label_key }}_type" id="{{ $key->label_key }}_type" class="form-select custom-select custom-dropdown" onclick="handleSelectionChange('{{ $key->label_key }}')" required>
                                            <option value="na" {{ $key->selected_type == 'na' ? 'selected' : '' }}>Not Applicable</option>
                                            <option value="attribute_name" {{ $key->selected_type == 'attribute_name' ? 'selected' : '' }} {{ !$attributeExists ? 'disabled' : '' }}>
                                                IC Attribute
                                            </option>
                                            <option value="formula_name" {{ $key->selected_type == 'formula_name' ? 'selected' : '' }}>Formula</option>
                                            <option value="custom_val" {{ $key->selected_type == 'custom_val' ? 'selected' : '' }}>Custom Type</option>
                                        </select>
                                        <input type="hidden" name="{{ $key->label_key }}_type_formula" id="{{ $key->label_key }}_type_formula" value="{{ ($key->selected_type == 'formula_name') ? $key->selected_value : '' }}" />
                                        <input type="hidden" name="{{ $key->label_key }}_type_custom_val" id="{{ $key->label_key }}_type_custom_val" value="{{ ($key->selected_type == 'custom_val') ? $key->selected_value : '' }}" />
                                    </div>
                                    <div id="{{ $key->label_key }}_container" class="col-sm-4">
                                        @if($key->selected_type == 'attribute_name')
                                        @php
                                        $attributeTrail = '';
                                        $attributeName = '';
                                        foreach ($attributes as $attribute) {
                                        if ($attribute->label_key === $key->label_key) {
                                        $attributeName = $attribute->attribute_name;
                                        $attributeTrail = $attribute->attribute_trail;
                                        break;
                                        }
                                        }
                                        @endphp
                                        <div class="input-group" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $attributeName }} ({{ $attributeTrail }})">
                                            <input type="text" name="{{ $key->label_key }}_attribute_name" class="form-control" value="{{ $attributeName }} ({{ $attributeTrail }})" readonly required />
                                        </div>
                                        @elseif($key->selected_type == 'formula_name')
                                        <select name="{{ $key->label_key }}_formula_name" class="form-control selectpicker" data-live-search="true" required>
                                            @foreach($formulas as $formula)
                                            <option value="{{ $formula->id }}" {{ $key->selected_value == $formula->id ? 'selected' : '' }}>{{ $formula->formula_name }}</option>
                                            @endforeach
                                        </select>
                                        @elseif($key->selected_type == 'custom_val')
                                        <input type="number" name="{{ $key->label_key }}_custom_val" class="form-control" value="{{ old($key->label_key . '_custom_val') ? old($key->label_key . '_custom_val') : (empty($key->selected_value) ? '0' : $key->selected_value) }}" min="0" max="5000000" required />
                                        @endif
                                    </div>
                                </div>
                                @endforeach

                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary me-2" id="updateButton">Update</button>
            <a href="{{ route('admin.ic-configuration.premium-calculation-configurator.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script src="{{ asset('admin1/js/ic-config/premiumcalculationconfigurator.js') }}"></script>
<script>
    var attributes = @json($attributes);
    var formulas = @json($formulas);
</script>
@endsection