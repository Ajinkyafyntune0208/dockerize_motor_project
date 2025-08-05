@extends('admin_lte.layout.app', ['activePage' => 'stage-count', 'titlePage' => __('Journey Stage Count')])
@section('content')
<div class="card card-primary">
    <div class="card-body">
        <form action="" method="GET">
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="list">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label class="required">Stage</label>
                        <select name="stage[]" multiple data-actions-box="true" data-style="btn btn-light" class="border selectpicker w-100" data-live-search="true" required>
                            @foreach ($stageDropDownValue as $stage)
                            @if (!empty('$stage') && $stage!="")
                            <option value="{{ $stage }}" {{ request()->stage && in_array($stage,request()->stage) ? 'selected' : '' }}>
                                {{ $stage }}
                            </option>
                            @endif
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Company</label>
                        <select name="company" id="" data-style="btn-sm btn-primary" data-actions-box="true" class="form-control select2" data-live-search="true">
                            <option value="" selected>show all</option>
                            @foreach ($companies as $c)
                            <option value="{{ $c->company_id }}" {{ request()->company && $c->company_id == request()->company  ? 'selected' : '' }}>
                                {{ $c->company_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <label class="required">From Date</label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="date" name="from_date" value="{{ old('from_date', request()->from_date) }}" required id="" class="datepickers form-control" placeholder="From" autocomplete="off">
                        </div>
                    </div>

                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <label class="required">To Date</label>
                        <input type="date" name="to_date" value="{{ old('to_date', request()->to_date) }}" required id="" class="datepickers form-control" placeholder="To" autocomplete="off">

                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (!empty($count))
<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table class="table-striped table">
                <thead>
                    <tr>
                        <th scope="col">Car</th>
                        <th scope="col">Bike</th>
                        <th scope="col">CV</th>

                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $count['car'] }}</td>
                        <td>{{ $count['bike'] }}</td>
                        <td>{{ $count['cv'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<p>No Records or search for records</p>
@endif
@endsection('content')
@push('scripts')
<script>
    $(document).ready(function() {
        $('.datepickers').datepicker({
            todayBtn: "linked",
            autoclose: true,
            clearBtn: true,
            todayHighlight: true,
            toggleActive: true,
            format: "yyyy-mm-dd"
        });

    });
</script>
@endpush