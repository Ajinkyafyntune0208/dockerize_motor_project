@extends('layout.app', ['activePage' => 'sql_runner', 'titlePage' => __('Sql Runner')])
@section('content')

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css" />

<style>
    :root{
    --primary: #1f3bb3;
    }
    .cus-cell {
        max-width: 30px; /* tweak me please */
        overflow : hidden;
        text-overflow: ellipsis;
    }
    .cell-exp:hover {
        max-width : none;
        white-space: normal;
        overflow: auto;
        text-align: center;
        text-overflow: ellipsis;
    }
    .btn-cus{
        height: 40px;
        max-width: 140px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
    .dat-select{
        background: #e9ecef;
        color: #c9c8c8;
        padding: 9px 12px !important;
    }
    select.form-control, .filter-option-inner-inner{
        color: black;
    }
    th {
        width: 30px;
        white-space: normal !important;
        text-align: center !important;
    }
</style>
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">SQL Runner</h4>
                        <form  action="" method="POST" id="sql" onsubmit="return validateForm()">
                            @csrf
                            <input type="hidden" name="per_page" id="per_page" value="">
                            <div class="align-items-center">
                                <label class="font-weight-bold mb-2 required" for="sql_query">Enter your Sql Query</label>
                                <div class="form-group">
                                    <p class="d-flex flex-row align-items-center">
                                        <span class="mx-2" style="font-size:16px;">SELECT</span>
                                        <span class="w-100">
                                            <textarea class="form-control" name="sql_query" id="sql_query"  rows="100" style="height: 100px;" required> {{ old('sql_query', request()->sql_query) }}</textarea>
                                            <label id="sql_validation" hidden class="text-danger">Sql Query is required!</label>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="row mx-5">
                                <div class="col-sm-1 ">
                                    <button type="submit" id="submit" onclick="unSetFunction()" class="btn btn-cus" value="unset"><i class="fa fa-code mx-1"></i>Run</button>
                                </div>
                                <div class="col-sm-8 mx-5">
                                    <input type="hidden" name="download" id="runsqldownload">
                                    <button type="submit" id="data" onclick="setFunction()" class="btn btn-cus" value="rundownload"><i class="fa fa-code mx-1"></i>Run and Download</button>
                                </div>
                           </div>
                        </form>
                    </div>
                </div>
            </div>
            @if (Session::has('errors'))
                <div class="alert alert-{{Session::get('class')}}">
                    {{Session::get('errors')}}
                </div>
            @endif

            @if ( isset($headings) && isset($records)  )
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            @if (empty($records))
                            <div class="alert alert-warning">No records found</div>
                            @else
                            <div class="table-responsive">
                                <table class="table table-striped" id="sql_table">
                                    @if (!empty($headings))
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            @foreach ($headings as $value)
                                                <th scope="col">{{$value}}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    @endif
                                    <tbody>
                                        @if (!empty($records))
                                            @foreach ($records as $data)
                                                <tr>
                                                    <th scope="row">{{$loop->iteration}}</th>
                                                    @foreach ($data as $value)
                                                        <td class="text-center">{{ $value}}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    {{-- <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.12.1/datatables.min.js"></script> --}}
    <script type="text/javascript" src="{{ asset('js/dataTables.buttons.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/jszip.min.js') }}"></script>
    {{-- <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script> --}}
    <script type="text/javascript" src="{{ asset('js/buttons.html5.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/buttons.print.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('#sql_table').DataTable({
                // dom: 'Bfrtip',
                dom: 'lPfBrtpi',
                buttons: ['csv', 'excel'],
                searchPane: true,
                search: {
                    search: ''
                }
            });
        });

    </script>
    <script>
        function validateForm() {
            var sqlQuery = document.getElementById('sql_query').value;
            if (sqlQuery.trim() === '') {
                $('#sql_validation').attr('hidden',false)
                return false;
            }
            return true;
        }
        function setFunction() {
            var dd = document.getElementById('data').value;
            console.log(dd);
            $('#runsqldownload').val(dd)
        }
        function unSetFunction() {
            var dd = document.getElementById('submit').value;
            console.log(dd);
            $('#runsqldownload').val(dd)
        }
    </script>
@endpush
