@extends('admin_lte.layout.app', ['activePage' => 'ckyc-wrapper-logs', 'titlePage' => __('CKYC Wrapper Logs')])
@section('content')
@if ($errors->any())
<div class="alert alert-danger">
    <ul class="list">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<div class="col-6 col-md-4 text-center">
    @if (session('error'))
    <div class="alert alert-danger mt-3 py-1">
        {{ session('error') }}
    </div>
    @endif
</div>
<div class="card card-primary">
    <div class="card-body">
        <form action="" method="GET">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Enquiry Id <span class="text-danger"> *</span></label>
                        <input class="form-control" id="enquiryId" name="enquiryId" type="text" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" required aria-required="true">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Company <span class="text-danger"> *</span></label>
                        <select class="form-control select2" name="company" data-live-search="true">
                            <option value="">Show All</option>
                            @foreach ($companies as $company)
                            <option value="{{ $company }}" {{ old('company', request()->company) == $company ? 'selected' : '' }}>
                                {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}
                            </option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <input type="hidden" name="paginate" value="30">
            </div>
            <div class="row">
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if (!empty($logs))
<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table class="table-striped table">
                <?php $i = 1; ?>
                @foreach ($logs as $log)

                @if($i === 1)
                <thead>
                    <tr class="align-center">
                        <th scope="col">#</th>
                        <th scope="col">Action</th>
                        @foreach($log->getAttributes() as $key => $value)
                        @php if (in_array($key, ['id', 'enquiry_id']))  continue; @endphp
                        <th scope="col">{{strtoupper($key)}}</th>
                        @endforeach
                    </tr>
                </thead>
                @endif
                @if($i === 1)
                <tbody>
                    @endif
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        @foreach($log->getAttributes() as $key => $value)
                        @if($key == 'id')
                        <td class="text-right">
                            <a class="btn btn-sm btn-primary" class="btn btn-primary float-end btn-sm" href="{{ url('admin/ckyc-wrapper-logs/' . $value) }}" target="_blank"><i class="fa fa-eye"></i></a>
                            <a target="_blank" href="{{ url('admin/ckyc-wrapper-logs-download/'.$log->id)}}" class="btn btn-sm btn-success">
                                <i class="fa fa-solid fa-download"></i>
                            </a>
                        </td>
                        @endif
                        @php if (in_array($key, ['id', 'enquiry_id'])) continue; @endphp
                        @if($key == 'failure_message')
                        <td scope="col">{{Str::substr($value, 0, 50)}}</td>
                        @else
                        <td scope="col">{{$value}}</td>
                        @endif
                        @endforeach

                    </tr>
                    @if($i === 1)
                </tbody>
                @endif
                <?php $i++; ?>
                @endforeach
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
        $('.table').DataTable();
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