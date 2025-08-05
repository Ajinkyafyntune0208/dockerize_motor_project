@extends('admin_lte.layout.app', ['activePage' => 'insurer-logo-priority-list', 'titlePage' => __('Insurer Logo Priority List')])

@section('content')
    <style>
        .btn-outline-primary {
            margin-bottom: 6px;
        }
    </style>

    @if (!empty($company))
        {{-- Business Type Selection --}}
        <div class="card card-primary">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p>Select Business Type</p>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control businessTypeSelect" required>
                            @foreach ($seller_type as $seller)
                                <option value="{{ $seller }}">{{ $seller }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- B2B Form --}}
        <form action="{{ route('admin.insurer_logo_priority_list.store') }}" method="POST" id="b2bInsurerForm">
            @csrf
            <input type="hidden" name="businessType" value="B2B">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">Priority</th>
                                    <th class="text-center">Insurer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 1; $i <= count($company); $i++)
                                    @php
                                        $selectedInsurer = collect($data)->firstWhere('priority', $i);
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $i }}</td>
                                        <td class="text-center">
                                            <select class="form-control insurer-select" name="insurers[]" required>
                                                <option value="">Nothing selected</option>
                                                @foreach ($company as $key => $value)
                                                    <option value="{{ $key }}"
                                                        {{ $selectedInsurer && $selectedInsurer['company_name'] === $value ? 'selected' : '' }}>
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
                    <button type="submit" class="btn btn-success btn-sm saveBtn" style="display:none;">Save</button>
                    {{-- <a href="{{ route('admin.insurer_logo_priority_list.create') }}" class="btn btn-danger btn-sm">Reset</a> --}}
                    <button type="button" class="btn btn-danger btn-sm resetBtn" data-form="#b2bInsurerForm">Reset</button>
                </div>
            </div>
        </form>

        {{-- B2C Form --}}
        <form action="{{ route('admin.insurer_logo_priority_list.store') }}" method="POST" id="b2cInsurerForm"
            style="display: none;">
            @csrf
            <input type="hidden" name="businessType" value="B2C">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">Priority</th>
                                    <th class="text-center">Insurer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 1; $i <= count($company); $i++)
                                    @php
                                        $selectedInsurer = collect($data1)->firstWhere('priority', $i);
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $i }}</td>
                                        <td class="text-center">
                                            <select class="form-control insurer-select-b2c" name="insurers[]" required>
                                                <option class = "ic" value="">Nothing selected</option>
                                                @foreach ($company as $key => $value)
                                                    <option class = "ic" value="{{ $key }}"
                                                        {{ $selectedInsurer && $selectedInsurer['company_name'] === $value ? 'selected' : '' }}>
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
                    <button type="submit" class="btn btn-success btn-sm saveBtn" style="display: none;">Save</button>
                    {{-- <a href="{{ url('admin/reset/insurer_logo_priority_list') }}" class="btn btn-danger btn-sm" >Reset</a> --}}
                    <button type="button" class="btn btn-danger btn-sm resetBtn" data-form="#b2cInsurerForm">Reset</button>
                </div>
            </div>
        </form>
    @else
        <p>No Records or search for records</p>
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
                $(formId).find('.saveBtn').hide();
            });
            function updateDropdowns(selectorClass) {
                $(selectorClass).each(function() {
                    let selected = [];
                    $(selectorClass).each(function() {
                        let val = $(this).val();
                        if (val && val !== '') {
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
            function initHandlers(selectorClass, formId) {
                updateDropdowns(selectorClass);
                $(selectorClass).change(function() {
                    updateDropdowns(selectorClass);
                    $(formId).find('.saveBtn').show();
                });
                $(formId).on('submit', function(e) {
                    let selected = [];
                    let duplicate = false;
                    $(selectorClass).each(function() {
                        let val = $(this).val();
                        if (val && val !== '') {
                            if (selected.includes(val)) {
                                duplicate = true;
                            }
                            selected.push(val);
                        }
                    });
                    if (duplicate) {
                        e.preventDefault();
                        alert(
                            'Duplicate insurers selected. Please ensure each insurer has a unique priority.');
                    }
                });
            }
            $('.businessTypeSelect').on('change', function() {
                const selectedType = $(this).val();
                if (selectedType === 'B2C') {
                    $('#b2bInsurerForm').hide();
                    $('#b2cInsurerForm').show();
                    initHandlers('.insurer-select-b2c', '#b2cInsurerForm');
                } else {
                    $('#b2bInsurerForm').show();
                    $('#b2cInsurerForm').hide();
                    initHandlers('.insurer-select', '#b2bInsurerForm');
                }
            }).trigger('change');
        });
    </script>
@endsection