@extends('layout.app', ['activePage' => 'IC_Error_Handler', 'titlePage' => __('Upload CSV File')])

@section('content')
<style>
    @media (min-width: 576px){
        .modal-dialog {
            /* max-width: 911px; */
            margin: 34px auto;
            word-wrap: break-word;
        }
    }
</style>
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif
              
               <div class="row mt-4">
                <div class="col-12 d-flex justify-content-end stretch-card">
                    
                </div>
            </div>
            
            <a href="{{ url()->previous() }}" class="btn btn-sm btn-primary"><i class="fa fa-arrow-circle-left"></i> Back</a><br><br>
            <div class="contrainer">
            <form action="{{ route('admin.cashless_garage.store') }}" method="post" class="row" enctype="multipart/form-data">@csrf
                <div class="row">
                    <div class="col-sm-3">
                        <label for="product" class="required">Product Type</label>
                        <select name="product" id="product" class="selectpicker w-100" data-style="btn btn-sm btn-primary" >
                            <option value=""></option>
                            <option value="car">Car</option>
                            <option value="bike">Bike</option>
                            <option value="pcv">PCV</option>
                            <option value="gcv">GCV</option>
                        </select>
                        @error('product')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-sm-3">
                        <label for="company_alias" class="required">Company Alias</label>
                        <select name="company_alias" id="company_alias" class="selectpicker w-100" data-style="btn btn-sm btn-primary" >
                            <option value=""></option>
                            @foreach($company_alias as $name)
                            <option value="{{ $name }}" {{ (old('company_alias', request()->company_alias) == $name ? 'selected' : '') }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('company_alias')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="active required" for="label">Select Excel File</label>
                            <br>
                            <input id="file" name="cashless_garage_file" type="file" placeholder="file"
                                value="{{ old('cashless_garage_file') }}">{{-- accept=".xlsx" --}}
                            @error('cashless_garage_file')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="d-flex justify-content-left">
                        <button type="submit" class="btn btn-outline-primary"
                            style="margin-top: 30px;">Submit</button>
                    </div>
                </div>
                </div>
            </form>
                <a href="{{url('Sample Cashless Garage Format.xlsx')}}">Download Excel File Format</a>
            </div>
            </div>
            
    </section>
</main>

@endsection
