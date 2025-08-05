@extends('admin_lte.layout.app', ['activePage' => 'cashless_garage', 'titlePage' => __('Cashless Garage')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.cashless_garage.index') }}" class="btn btn-primary mb-4"><i class="fa fa-solid fa-arrow-left"></i></a>
<div class="card card-primary">
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.cashless_garage.store') }}" method="POST" enctype="multipart/form-data">@csrf
            <div class="row">
                <!-- text input -->
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Product Type</label>
                        <select name="product" id="product" class="form-control select2" >
                            <option value="">Nothing Selected</option>
                            <option value="car">Car</option>
                            <option value="bike">Bike</option>
                            <option value="pcv">PCV</option>
                            <option value="gcv">GCV</option>
                        </select>
                        @error('product')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Company Alias</label>
                        <select name="company_alias" id="company_alias" class="form-control select2">
                            <option value="">Nothing Selected</option>
                            @foreach($company_alias as $name)
                            <option value="{{ $name }}" {{ (old('company_alias', request()->company_alias) == $name ? 'selected' : '') }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('company_alias')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Select Excel File</label>
                        <input class="btn btn-primary" id="file" name="cashless_garage_file" type="file" placeholder="file"
                                value="{{ old('cashless_garage_file') }}">{{-- accept=".xlsx" --}}
                        @error('cashless_garage_file') <span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                
                <div class="col-sm-6 text-right">
                    <div class="d-flex justify-content-center">
                        <button type="submit" style="margin-left:400px" class="btn btn-primary">Submit</button>

                    </div>
                </div>
                <a class="btn btn-primary ml-2" href="{{url('Sample Cashless Garage Format.xlsx')}}">Download Excel File Format</a>
        </form>
    </div>
</div>
@endsection('content')