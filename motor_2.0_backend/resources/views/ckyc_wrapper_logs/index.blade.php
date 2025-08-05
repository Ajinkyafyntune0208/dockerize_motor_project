@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Logs')])
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">CKYC Wrapper Logs</h4>
                    <p class="card-description">

                    </p>
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
                       <div class="col-6 col-md-4 text-center">
                        @if (session('error'))
                        <div class="alert alert-danger mt-3 py-1">
                            {{ session('error') }}
                        </div>
                        @endif
                    </div>
                        <!-- @csrf -->
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active required" for="enquiryId">Enquiry Id</label>
                                    <input class="form-control" id="enquiryId" name="enquiryId" type="text"
                                        value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                                        placeholder="Enquiry Id" required aria-required="true">
                                </div>

                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Company</label>
                                    <select class="selectpicker" name="company" data-live-search="true">
                                        <option value="">Show All</option>
                                        @foreach ($companies as $company)
                                        <option value="{{ $company }}" {{ old('company', request()->company) == $company
                                            ? 'selected' : '' }}>
                                            {{ strtoupper(str_replace('_', ' ', $company ?? '')) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-sm-3 text-right">
                                <div class="form-group">
                                    <button class="btn btn-outline-primary" type="submit" style="margin-top: 30px;"><i
                                            class="fa fa-search"></i> Search</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-12 grid-margin stretch-card">

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
                                    <?php if(in_array($key, ['id', 'enquiry_id'])) continue; ?>
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
                                        <a class="btn btn-sm btn-primary" class="btn btn-primary float-end btn-sm"
                                            href="{{ url('admin/ckyc-wrapper-logs/' . $value) }}" target="_blank"><i
                                                class="fa fa-eye"></i></a>
                                        <a target="_blank" href="{{ url('admin/ckyc-wrapper-logs-download/'.$log->id)}}"
                                            class="btn btn-sm btn-success">
                                            <i class="fa fa-solid fa-download"></i>
                                        </a>
                                    </td>
                                    @endif
                                    <?php if(in_array($key, ['id', 'enquiry_id'])) continue; ?>
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
        </div>
    </div>
</div>

@endsection
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