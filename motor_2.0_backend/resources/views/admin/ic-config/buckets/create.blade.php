@extends('admin_lte.layout.app', ['activePage' => 'create-buckets', 'titlePage' => __('Create Buckets')])
@section('content')
<style>
    
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}

</style>
<div class="card">
    <div class="card-body">
        <form action="{{route('admin.ic-configuration.buckets.create')}}" method="post">
            @csrf
            <!-- @csrf -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="bucketName">Bucket Name</label>
                        <input class="form-control" id="bucketName" name="bucketName" type="text"
                            value="{{ old('bucketName', request()->bucketName ?? null) }}"
                            placeholder="Bucket Name" required pattern="[A-Za-z0-9_]+" title="Only alphanumeric characters and underscores are allowed.">
                            @error('bucketName')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active required" for="discount">Discount (%)</label>
                        <input class="form-control" id="discount" name="discount" type="number"
                            value="{{ old('discount', request()->discount ?? null) }}"
                            placeholder="Discount" required step="0.01" min="0" max="100">
                            @error('discount')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="mandatory-addons">Mandatory Addons</label>
                        <select id="mandatory-addons" name="mandatoryAddons[]" multiple data-style="btn btn-secondary" data-actions-box="true" class="selectpicker addons-select w-100" data-live-search="true">
                            @foreach ($addons as $value)
                                <option value="{{$value->id}}">{{$value->label_name}}</option>
                            @endforeach
                        </select>
                        @error('mandatoryAddons')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="optional-addons">Optional Addons</label>
                        <select id="optional-addons" name="optionalAddons[]" multiple data-style="btn btn-secondary" data-actions-box="true" class="selectpicker addons-select w-100" data-live-search="true">
                            @foreach ($addons as $value)
                                <option value="{{$value->id}}">{{$value->label_name}}</option>
                            @endforeach
                        </select>
                        @error('optionalAddons')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="excluded-addons">Excluded Addons</label>
                        <select id="excluded-addons" name="excludedAddons[]" multiple data-style="btn btn-secondary" data-actions-box="true" class="selectpicker addons-select w-100" data-live-search="true">
                            @foreach ($addons as $value)
                                <option value="{{$value->id}}">{{$value->label_name}}</option>
                            @endforeach
                        </select>
                        @error('excludedAddons')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-3">

                    <div class="form-group">
                        <button class="btn btn-primary" type="submit" style="margin-top: 30px;">Save</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{asset('admin1/js/ic-config/bucket.js')}}"></script>
@endsection
