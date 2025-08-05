@extends('admin_lte.layout.app', ['activePage' => 'ic-return-url', 'titlePage' => __('IC Return URL')])
@section('content')
<div class="card card-primary">
    <!-- form start -->
    <div class="card-body">
        <form action=" ic-return-url" >
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Section <span class="text-danger"> *</span></label>
                        <select class="form-control select1" name="category" data-live-search="true" id="category" onchange="OptionCheck()">
                            <option value="">select</option>
                            @foreach ($categories as $category)
                            <option value="{{ $category }}" {{ old('category', request()->category) == $category ? 'selected' : '' }}>
                                {{ strtoupper(str_replace('_', ' ', $category ?? '')) }}
                            </option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Company <span class="text-danger"> *</span></label>
                        <select class="form-control select2" name="company" data-live-search="true" id="company" onchange="OptionCheck()">
                            <option value="" >select</option>
                            @foreach ($companies as $company)
                            <option value="{{ $company }}" {{ old('company', request()->company) == $company ? 'selected' : '' }}>
                                {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}
                            </option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Make</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (!empty($returnurl) && is_string($returnurl))
<div class="card">
    <div class="card-body">
           <h2>Return URL</h2>
           <p id = "result"><i class="text-info fa fa-copy ml-2" role="button"></i>{{ $returnurl }}</p>
           
    </div>
</div>
@else
<p>No Records or search for records</p>
@endif
@endsection('content')

@section('scripts')

<script>

    function OptionCheck() {
            let company_selected = document.getElementById("company").value;
            let category_selected = document.getElementById("category").value;
            let finalresult = document.getElementById("result").innerHTML;
            let arr=finalresult.split('/');
            arr[arr.length-1]=company_selected;
            arr[arr.length-3]=category_selected;
            let str = arr.toString();
            str = str.replaceAll(",", "/");
            document.getElementById("result").innerHTML = str;
        }

   $(document).ready(function()
    {
        $(document).on('click', '.fa.fa-copy', function() {
            var text = $(this).parent("#result").text();
            const elem = document.createElement('textarea');
            elem.value = text.trim();
            document.body.appendChild(elem);
            elem.select();
            document.execCommand('copy');
            document.body.removeChild(elem);
            alert('Text copied...!')
        });
    });
</script>
@endsection



