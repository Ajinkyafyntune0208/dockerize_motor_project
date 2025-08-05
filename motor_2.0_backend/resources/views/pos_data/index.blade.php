@extends('layout.app', ['activePage' => 'pos-data', 'titlePage' => __('POS Data')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"> POS Data</h5>
                        <form enctype="multipart/form-data" method="GET">
                            <div class="row ">
                                <div class="col-md-4 ">
                                    <div class="form-group">
                                        <label class="required">Select Company Name :</label>
                                        <select required name="table_name" data-style="btn-sm btn-primary" id="table_data"
                                            data-actions-box="true" class="selectpicker" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            @foreach ($company as $company_ic)
                                                <option
                                                    {{ request()->table_name == $company_ic->company_alias ? 'selected' : '' }}
                                                    value="{{ $company_ic->company_alias }}">
                                                    {{ $company_ic->company_alias }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('table_name')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4 ">
                                    <div class="form-group">
                                        <button class="btn btn-primary btn-sm" type="submit" ><i
                                                class="fa fa-search"></i> Search</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    @if (!empty($table_data))
                        <div class="card-body">
                            <hr>
                            <div class="table-responsive">
                                <table class="table-striped table" id="table_detail">
                                    <thead>
                                        @if (!empty($table_data[0]))
                                            <tr>
                                                <th>view</th>
                                                @foreach ($table_data[0] as $key => $table)
                                                    <th scope="col">{{ $key }}</th>
                                                @endforeach
                                            </tr>
                                        @else
                                            <p>No data available in table</p>
                                        @endif
                                    </thead>
                                    <tbody>
                                        @foreach ($table_data as $key => $table)
                                            <tr>
                                                <td> <a href="{{ url('admin/pos-data/' . $table->ic_mapping_id . '?table=' . request()->table_name) }}"
                                                        class="btn btn-primary center float-end btn-sm" target="_blank"><i
                                                            class="fa fa-eye"></i></a></td>
                                                @foreach ($table as $k => $v)
                                                    <td class="" scope="row">{{ $v }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('js/jszip.min.js') }}"></script>
    <script src="{{ asset('js/pdfmake.min.js') }}"></script>
    <script src="{{ asset('js/vfs_fonts.js') }}"></script>
    <script src="{{ asset('js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('js/buttons.print.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#table_detail').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                        extend: "excelHtml5",
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                    {
                        extend: "csvHtml5",
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                ]
            });
        });
    </script>
@endpush
