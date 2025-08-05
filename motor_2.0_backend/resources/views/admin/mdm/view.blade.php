@extends('layout.app', ['activePage' => 'master-product', 'titlePage' => __('Master Policy')])
@section('content')
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
    /* select.form-control, .filter-option-inner-inner{
        color: black;
    } */
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
                        <h4 class="card-title">MDM Sync Logs</h4>
                        <form  action="" method="GET" >
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="list">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @csrf
                            <input type="hidden" name="per_page" id="per_page" value="">
                            <div class="row align-items-center">
                                <div class="col-sm-4 form-group">
                                    <label class="font-weight-bold" for="bid">Master Name</label>
                                    <input type="text" class="form-control" name="master_name" id="master_name" value="{{ old('master_name', request()->master_name) }}" >
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="">From Date</label>
                                    <input type="text" name="from_date"  
                                        value="{{ old('from_date', request()->from_date) }}"
                                        id="" class="datepickers form-control" placeholder="From"
                                        autocomplete="off"
                                    >                                   
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="">To Date</label>
                                    <input type="text" name="to_date" 
                                        value="{{ old('to_date', request()->to_date) }}" 
                                        id="" class="datepickers form-control" placeholder="To" 
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="">Status</label>
                                    <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="status" id="status">
                                        <option value="" {{ isset(request()->status) ? '' : 'selected'}}> Select Status</option>
                                        <option {{old('status', request()->status) == 'success' ? 'selected' : '' }} value="success">Success</option>
                                        <option {{old('status', request()->status) == 'failure' ? 'selected' : '' }} value="failure">Failed</option>
                                        <option {{old('status', request()->status) == 'pending' ? 'selected' : '' }} value="pending">Pending</option>
                                    </select>
                                </div>
                                <input type="hidden" name="form_submit" value=true>
                                <div class="col-md-2">
                                    <button type="submit" id="submit" class="btn btn-cus"><i class="fa fa-search m-1"></i> Search</button>
                                </div>
                            </div>   
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        
                        @if ($mdmlogs->isEmpty())
                            <div class="alert alert-warning mt-3"> <i class="fa fa-warning"></i> No records found!</div>
                        @else
                            @php
                                $perPageValues = [25,50,75,100];
                            @endphp
                            <div class="d-flex col-1">
                                <label class="mx-2">Show</label>
                                <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 mx-2" data-live-search="true" style="width:100px;" name="per_page" id="per_page_hidden"> 
                                    <option value="" {{ isset(request()->per_page) ? '' : 'selected'}}> Select</option>
                                    @foreach ($perPageValues as $item)
                                        <option value="{{$item}}" {{ old('per_page', request()->per_page ) == $item ? 'selected' : ($item == 25 ? 'selected' : '')   }}>{{$item}}</option>
                                    @endforeach
                                </select>
                                <label>entries</label>
                            </div>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="mdm_table">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Master ID</th>
                                                <th style="width: 50px;" scope="col">Master Name</th>
                                                <th scope="col">Total Rows</th>
                                                <th scope="col">Rows Inserted</th>
                                                <th scope="col">Message</th>
                                                <th scope="col">Created Date</th>
                                                <th scope="col">Updated Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($mdmlogs as $key => $value)
                                                <tr>
                                                    <th scope="row">{{$loop->iteration}}</th>
                                                    <td class="text-center text-{{($value->status == 'success') ? 'success' : (($value->status == 'pending') ? 'warning' : 'danger')}}">
                                                        {{ strtoupper($value->status) }}
                                                    </td>
                                                    <td class="text-center">{{ $value->master_id }}</td>
                                                    <td class="text-center">{{ $value->master_name ?? 'NA' }}</td>
                                                    <td class="text-center">{{ $value->total_rows }}</td>
                                                    <td class="text-center">{{ $value->rows_inserted }}</td>
                                                    <td>{{$value->message }}</td>
                                                    <td class="text-center">{{ $value->created_at }}</td>
                                                    <td class="text-center">{{ $value->updated_at }}</td>
                                                </tr>
                                            @endforeach
                                        
                                        </tbody>
                                    </table>
                                </div>
                    
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">

                                <p scope="col">Total Result found: {{$mdmlogs->total()}}</p>
                                <p scope="col">Showing records per page: {{$mdmlogs->count()}}</p>
                                <div scope="col">
                                    @if(!$mdmlogs->isEmpty())
                                        {{ $mdmlogs->appends(request()->query())->links() }}
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let perpage = $('#per_page_hidden').val();
            console.log(perpage);
            $('#mdm_table').DataTable({
                dom: 'Pfrt',
                "pageLength": perpage,
                searchPane: true,
                search: {
                    search: ''
                }
            });
            $('#per_page_hidden').on('change', function() {
                var selectedValue = $(this).val();
                var news = $('#per_page').val(selectedValue);
                $('#submit').click();
            });
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
