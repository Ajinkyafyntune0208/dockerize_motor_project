@extends('layout.app', ['titlePage' => 'Preferred RTO'])

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Preferred RTO</h5>
        </div>
        <div class="card-body">

        {{--<div class="row mt-4">
                <div class="col-12 text-right">
                    <a href="{{ route('admin.usp.create',['usp_type' => 'car']) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
                </div>
            </div>--}}
            @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
            @endif
            <form action="{{ route('admin.rto-prefered.store') }}" method="POST"> @csrf
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th scope="col">Sr. No.</th>
                            <th scope="col">USP Description</th>
                            <th scope="col">Priority</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prefred_cities as $key => $prefred_city)
                        <tr>
                            <th scope="col">{{ $key + 1 }}
                                <input type="hidden" name="preferred_city_id[{{ $key }}]" value="{{ old('preferred_city_id.' . $key , $prefred_city->preferred_city_id ) }}">
                            </th>
                            <td scope="col">
                                <select name="city_name[{{ $key }}]" class="selectpicker w-50 @error('city_name') is-invalid @enderror" data-style="btn-primary" data-live-search="true">
                                    <option value="{{ $prefred_city->city_name ?? '' }}" selected>{{ $prefred_city->city_name ?? 'Select Any One' }}</option>
                                    @foreach($master_cities as $city)
                                    <option {{ old('city_name.' . $key, $prefred_city->city_name) == ($city->city_name ?? null) ? 'selected' : '' }} value="{{ $city->city_name ?? '' }}">{{ $city->city_name ?? '' }}</option>
                                    @endforeach
                                </select>
                                @error('city_name.' . $key) <span class="text-danger">{{ $message }}</span> @enderror
                            </td>
                            <td scope="col">
                                <!-- {{ $prefred_city->priority }} -->
                                <input type="text" name="priority[{{ $key }}]" class="form-control only-number" value="{{ old('priority.'. $key, $prefred_city->priority) }}">
                                @error('priority.' . $key) <span class="text-danger">{{ $message }}</span> @enderror
                            </td>
                            <td scope="col" class="text-right">
                                <!-- <form action="{{ route('admin.rto-prefered.destroy', [ $prefred_city->preferred_city_id, 'usp_type' => 'car' ]) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @ csrf @ method('DELETE') -->
                                <div class="btn-group">
                                    <!-- <a href="{{ route('admin.usp.show', [ $prefred_city->preferred_city_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                    <!-- <a href="{{ route('admin.rto-prefered.edit', [ $prefred_city->preferred_city_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a> -->
                                    <button type="submit" class="btn delete_records btn-sm btn-outline-danger" data-url="{{ route('admin.rto-prefered.destroy', $prefred_city->preferred_city_id) }}"><i class="fa fa-trash"></i></button>
                                </div>
                                <!-- </form> -->
                            </td>
                        </tr>
                        @endforeach
                        <tr>
                            <th scope="col">
                                <input type="hidden" name="preferred_city_id[{{ count($prefred_cities) }}]">
                            </th>
                            <td scope="col">
                                <!-- {{ $prefred_city->city_name }} -->
                                <select name="city_name[{{ count($prefred_cities) }}]" class="selectpicker w-50 @error('city_name') is-invalid @enderror" data-style="btn-primary" data-live-search="true" required>
                                    <option value="" selected>Select Any One</option>
                                    @foreach($master_cities as $city)
                                    <option {{ old('city_name.' . (count($prefred_cities))) == ($city->city_name ?? null) ? 'selected' : '' }} value="{{ $city->city_name ?? '' }}">{{ $city->city_name ?? '' }}</option>
                                    @endforeach
                                </select>
                                @error('city_name.' . (count($prefred_cities))) <span class="text-danger">{{ $message }}</span> @enderror
                            </td>
                            <td scope="col">
                                <input type="text" name="priority[{{ count($prefred_cities) }}]" class="form-control only-number" value="{{ old('priority.'. $key) }}" required>
                                @error('priority.' . (count($prefred_cities))) <span class="text-danger">{{ $message }}</span> @enderror
                            </td>
                            <td scope="col" class="text-right">
                                <div class="btn-group">
                                    <!-- <a href="{{ route('admin.usp.show', [ $prefred_city->preferred_city_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a> -->
                                    <!-- <a href="{{ route('admin.rto-prefered.edit', [ $prefred_city->preferred_city_id, 'usp_type' => 'car' ]) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a> -->
                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="fa fa-save"></i> Save</button>
                                </div>
                                <!-- </form> -->
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</main>
<form action="" class="delete_from" method="post"> @csrf @method('DELETE')</form>
<!--  -->
@endsection
@push('scripts')
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

        // $.ajax({
        //     url: 
        // })
    });
</script>
@endpush