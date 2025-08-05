@extends('layout.app', ['activePage' => 'push-api', 'titlePage' => __('Push Api Data')])

@section('content')
    <style>
        @media (min-width: 576px) {
            .modal-dialog {
                /* max-width: 911px; */
                margin: 34px auto;
                word-wrap: break-word;
            }
        }
    </style>
    <main class="container-fluid">
        <section class="mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Push Api Data</h5>
                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif
                    <div class="form-group">
                        <form action="" method="get">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <Label>From Date</Label>
                                        <input class="form-control" id="" name="from" type="date"
                                            value="{{ old('from', request()->from) }}" required placeholder="From"
                                            autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <Label>To Date</Label>
                                        <input class="form-control" id="" name="to" type="date"
                                            value="{{ old('to', request()->to) }}" required placeholder="To"
                                            autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="enquiryId" class="required">Enquiry ID</label>
                                <input type="text" name="enquiryId"  class="form-control" placeholder="Search Equiry Id" required>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <Label class="required">View Type</Label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="type">
                                            <option {{ old('type', request()->type) == 'view' ? 'selected' : '' }}>
                                                {{ 'view' }}</option>
                                            <option {{ old('type', request()->type) == 'excel' ? 'selected' : '' }}>
                                                {{ 'excel' }}</option>
                                        </select>
                                    </div>
                                </div>
                                
                            </div>
                            <div class="row">
                            <div class="col-md-3 right">
                                    <div class="d-flex align-items-center h-100">
                                        <input class="btn btn-outline-info btn-sm w-100" type="submit">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table-striped table" id="push_data_api_table">
                            <thead>
                                <tr>
                                    <th scope="col">EnquiryID</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Request / Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gramcover_data as $key => $data)
                                    <tr>
                                        <td scope="col">{{ $data->user_product_journey_id }}</td>
                                        <td scope="col">{{ $data->status }}</td>
                                        <td scope="col">{{ $data->created_at->format('d-m-Y H:s:i A') }}</td>
                                        <td scope="col"><a class="btn btn-sm btn-info view"
                                                href="{{ route('admin.push-api.show', $data->id) }}" target="_blank"><i
                                                    class="fa fa-eye"></i></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ro mt-4">
                            <div class=" d-flex justify-content-end stretch-card">
                                @if (!empty($gramcover_data))
                                    {{ $gramcover_data->links() }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
    </main>

    <!-- Modal -->
    {{-- <div class="modal fade" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Request / Response View <span></span></h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <h2>Status - <span id="showstatus"></span></h2>
                        <br>
                        <hr>
                        <h2>Token</h2>
                        <span id="showtoken"></span> <br><br>
                        <h2>Request</h2>
                        <hr>
                        <pre>
                            <span id="showdata"></span> <br><br>
                        </pre>
                        <hr>
                        <h2>Response</h2>
                        <pre>
                            <span id="showresponse"></span>
                        </pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button class="btn btn-secondary" data-dismiss="modal" type="button">Close</button> -->
                </div>

            </div>
        </div>
    </div> --}}
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

            $('#push_data_api_table').DataTable({
                "lengthMenu": [
                    [-1],
                    ["All"]
                ],
                "paging": false,
                "info": false,
                
            });
        });
    </script>
    <script>
        $(document).on('click', '.view', function() {
            var data = $(this).attr('data');
            var token = $(this).attr('token');
            var response = $(this).attr('response');
            var status = $(this).attr('status');
            var jsonObj = JSON.parse(data);
            var jsonPretty = JSON.stringify(jsonObj, null, '\t');

            $('#showdata').html(jsonPretty);
            $('#showtoken').html(token);
            $('#showresponse').html(response);
            $('#showstatus').html(status);
        });
    </script>
@endpush
