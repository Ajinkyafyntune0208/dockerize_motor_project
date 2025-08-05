@extends('admin_lte.layout.app', ['activePage' => 'ckyc-logs', 'titlePage' => __('Update Registration Date')])
@section('content')
<style>
    .btn-outline-primary {
        margin-bottom: 6px;
    }
</style>
<div class="card card-primary">
    <div class="card-body">
        <div id="successMessage" class="alert alert-success text-center" style="display: none;">
            
        </div>

        <div id="failureMessage" class="alert alert-danger text-center" style="display: none;">
           
        </div>
        <div class="form-group">
            <form action="" method="get">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <Label class="required">Enquiry Id</Label>
                            <input type="text" name="enquiry_id"
                                value="{{ request()->query('enquiry_id') ?? '' }}" required
                                class="form-control check select2 " placeholder="Enquiry ID" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center h-100">
                            <input type="submit" class="form-control btn btn-outline-primary mt-3 btn-sm w-100">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if (auth()->user()->can('update_registration_date.show'))
        <div class="row">
            <div class="col-md-4">
                <h3>Registration Date</h3>
                <div class="col-md-6">
                    <input id="registration_date" type="date" class="form-control update-field" name="registration_date"
                        value="{{ old('registration_date', $registration_date) }}" required>
                </div>
            </div>

            <div class="col-md-4">
                <h3>Manufacture Date</h3>
                <div class="col-md-6">
                    <input id="manufacture_date" type="month" class="form-control update-field" name="manufacture_date"
                        value="{{ old('manufacture_date', $manf_date) }}" required>
                </div>
            </div>

            <div class="col-md-4">
                <h3>Invoice Date</h3>
                <div class="col-md-6">
                    <input id="invoice_date" type="date" class="form-control update-field" name="invoice_date"
                        value="{{ old('invoice_date', $invoice_date) }}" required>
                </div>
            </div>

            @if (auth()->user()->can('update_registration_date.edit'))
            <div class="row m-3">
                <div class="col-md-12 text-center">
                    <button id="updateBtn" class="btn btn-success btn-sm" style="display: none;">Update</button>
                </div>
            </div>
            @endif
        </div>
        @else
        @if (request()->has('enquiry_id') && empty($registration_date) && empty($manf_date) && empty($invoice_date))
        <div class="row justify-content-center align-items-center">
            <div class="col-auto">
                <h8 class="text-center">No Date Found</h8>
            </div>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
@section('scripts')
<script>
    const saveDate = "{{ route('admin.update-registration-date.save-registration-date') }}";
</script>

<script src="{{asset('admin1/js/update-registration-date/save-date.js')}}"></script>
@endsection