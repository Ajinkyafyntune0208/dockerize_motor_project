@extends('layout.app', ['activePage' => 'logs', 'titlePage' => __('Journey Stage Count')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Journey Stage Count</h4>
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
                            <!-- @csrf -->
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="required">Stage</label>
                                        <select name="stage[]" multiple data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100"
                                            data-live-search="true" required>
                                            @foreach ($stageDropDownValue as $stage)
                                                @if (!empty('$stage') &&  $stage!="")
                                                    <option value="{{ $stage }}" {{ request()->stage && in_array($stage,request()->stage) ? 'selected' : '' }}>
                                                        {{ $stage }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Company</label>
                                        <select name="company" id="" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            <option value=""  selected>show all</option>
                                            @foreach ($companies as $c)
                                            <option value="{{ $c->company_id }}" {{ request()->company && $c->company_id == request()->company  ? 'selected' : '' }}>
                                                {{ $c->company_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="" class="required">From Date</label>
                                                <input type="text" name="from_date"   value="{{ old('from_date', request()->from_date) }}"
                                                required id="" class="datepickers form-control" placeholder="From" autocomplete="off">                                            </div>                                            <div class="col-md-6">
                                                <label for="" class="required">To Date</label>
                                                <input type="text" name="to_date" value="{{ old('to_date', request()->to_date) }}" required id="" class="datepickers form-control" placeholder="To" autocomplete="off">
                                            </div>
                                        </div>
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
            </div>
        </div>
    </div>

@endsection
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
