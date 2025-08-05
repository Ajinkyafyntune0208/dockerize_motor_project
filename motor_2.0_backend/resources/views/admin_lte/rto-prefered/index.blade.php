@extends('admin_lte.layout.app', ['activePage' => 'rto-prefered', 'titlePage' => __('Preferred RTO')])
@section('content')
<div class="card">

    <div class="card-body">
    <form action="{{ route('admin.rto-prefered.store') }}" method="POST"> @csrf
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>USP Description</th>
                    <th>Priority</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                @foreach($prefred_cities as $key => $prefred_city)
                <tr>
                    <td>{{++$key}}<input type="hidden" name="preferred_city_id[{{ $key }}]" value="{{ old('preferred_city_id.' . $key , $prefred_city->preferred_city_id ) }}"></td>
                    <td><select class="form-control select2" name="city_name[{{ $key }}]">
                            {{-- <option selected>Open this select active </option> --}}
                                <option value="{{ $prefred_city->city_name ?? '' }}" selected>{{ $prefred_city->city_name ?? 'Select Any One' }}</option>
                                    @foreach($master_cities as $city)
                                    <option {{ old('city_name.' . $key, $prefred_city->city_name) == ($city->city_name ?? null) ? 'selected' : '' }} value="{{ $city->city_name ?? '' }}">{{ $city->city_name ?? '' }}</option>
                                    @endforeach
                        </select></td>
                    <td><input type="text" name="priority[{{ $key }}]" class="form-control only-number" value="{{ old('priority.'. $key, $prefred_city->priority) }}">
                                @error('priority.' . $key) <span class="text-danger">{{ $message }}</span> @enderror</td>
                    <td>
                        <div class="btn-group btn-group-toggle">
                            @can('preferred_rto.delete')
                           <!-- <form action="{{ route('admin.broker.destroy', $prefred_city) }}" method="post" onsubmit="return confirm('Are you sure..?')">@csrf @method('delete') -->
                                 <button type="submit" id="delete_records" class="btn delete_records btn-sm btn-outline-danger" data-url="{{ route('admin.rto-prefered.destroy', $prefred_city->preferred_city_id) }}"><i class="fa fa-trash"></i></button>

                            <!-- </form> -->
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
                <tr>
                    <td><input type="hidden" name="preferred_city_id[{{ $key }}]" value="{{ old('preferred_city_id.' . $key , $prefred_city->preferred_city_id ) }}"></td>
                    <td>
                    <input type="hidden" name="preferred_city_id[{{ count($prefred_cities) }}]">
                        <select name="city_name[{{ count($prefred_cities) }}]" class="selectpicker w-50 @error('city_name') is-invalid @enderror" data-style="btn-primary" data-live-search="true" required>
                                    <option value="" selected>Select Any One</option>
                                    @foreach($master_cities as $city)
                                    <option {{ old('city_name.' . (count($prefred_cities))) == ($city->city_name ?? null) ? 'selected' : '' }} value="{{ $city->city_name ?? '' }}">{{ $city->city_name ?? '' }}</option>
                                    @endforeach
                                </select>
                                @error('city_name.' . (count($prefred_cities))) <span class="text-danger">{{ $message }}</span> @enderror
                    </td>
                    <td>
                        <input type="text" name="priority[{{ count($prefred_cities) }}]" class="form-control only-number" value="{{ old('priority.'. $key) }}" required>
                        @error('priority.' . (count($prefred_cities))) <span class="text-danger">{{ $message }}</span> @enderror
                    </td>
                   <td>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary">Submit</button>

                    </div>
                   </td>
                </tr>

            </tbody>

        </table>
        </form>
    </div>
</div>
<form action="" class="delete_from" method="post"> @csrf @method('DELETE')</form>

@endsection('content')
@section('scripts')
<script>
    $(document).ready(function() {
        $('.only-number').keyup(function() {
            this.value = this.value.replace(/[^0-9\.]/g,'');
        })
        $('.delete_records').click(function(e) {
            e.preventDefault();
            if (confirm('Are you wants to delete record..?')) {
                console.log($(this).attr('data-url'));
                $('.delete_from').attr('action', $(this).attr('data-url'));
                $('.delete_from').submit();
            }
        });
    });
</script>
@endsection('scripts')
