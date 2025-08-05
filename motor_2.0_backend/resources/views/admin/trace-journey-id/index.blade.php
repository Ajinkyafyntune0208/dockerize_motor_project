@extends('layout.app', ['activePage' => 'Get Trace ID', 'titlePage' => __('Get Trace ID')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">

                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="card-body">
                        <h4 class="card-title text-uppercase"> Get Trace ID
                            @if (!empty(request()->userInput))
                                &nbsp;&nbsp;<i role="button" data-type="userInput" class="text-info ml-2 fa fa-copy"></i>
                            @endif
                        </h4>
                        <p class="card-description"></p>

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
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="active required" for="userInput">User Input</label>
                                        <input id="userInput" name="userInput" type="text"
                                            value="{{ old('userInput', request()->userInput ?? null) }}"
                                            class="form-control" placeholder="User Input" required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Find Using</label>
                                        <select name="type" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            @foreach ($options as $key => $op)
                                                <option value="{{$key}}" {{ old('type', request()->type) === $key ? 'selected' : '' }}>{{$op}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2">
                                    <button title='Find Enquiry Id' type="submit"
                                        class="btn btn-outline-info btn-sm w-100">
                                        Search <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            @if (!empty($data['encrypted_journey_id']) || request()->type == 'breakin_policy_number')
                <div class="row my-5">
                    <div class="col-md-12 col-lg-12">
                        <div class="card" id="encrypted_journey_id">
                            <div class="card-body">
                                <h4 class="card-title text-uppercase">Enquiry ID
                                    @if (!empty($data['encrypted_journey_id']))
                                        &nbsp;&nbsp;<i role="button" data-type="encrypted_journey_id_copy"
                                            class="text-info ml-2 fa fa-copy"></i>
                                        &nbsp;&nbsp; <a title='Open Journey Data' target='_blank'
                                            href="{{ route('admin.journey-data.index', ['enquiry_id' => $data['encrypted_journey_id']]) }}"><i
                                                role="button" class="text-info fa fa-external-link"></i></a>
                                    @endif
                                </h4>

                                @if (!empty($data['encrypted_journey_id']))
                                    <pre id="encrypted_journey_id_copy">{{ $data['encrypted_journey_id'] }}</pre>
                                @endif

                                @if (!empty(request()->userInput) && empty($data['encrypted_journey_id']))
                                    <div class="alert alert-danger text-center">
                                        <h5>Sorry, No enquiry id was found associated with provided input.</h5>
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-lg-12 grid-margin stretch-card">

                @if (!empty($traceIdDetails) && count($traceIdDetails) > 0)
                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table id='trace-enquiry-table' class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Enquiry ID</th>
                                            <th scope="col">Vehicle Number</th>
                                            <th scope="col">Breakin ID</th>
                                            <th scope="col">Payment Status</th>
                                            <th scope="col">Policy Number</th>
                                            <th scope="col">PDF</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($traceIdDetails as $data)
                                            <tr>
                                                <td scope="row">{{ $loop->iteration }}</td>
                                                <td>{{ $data->TraceID }} <i class="text-info fa fa-copy ml-2" role="button" data-copy="{{$data->TraceID}}"></i></td>
                                                <td>{{ $data->RCNumber }}</td>
                                                <td>{{$data->breakinNumber}}</td>
                                                <td>{{ empty($data->status) ? 'PAYMENT NOT INITIATED' : $data->status }}
                                                </td>
                                                <td>{{ $data->policyNumber }}</td>
                                                @if (!empty($data->pdfURL))
                                                    <td><a target='_blank' class='p-3 btn text-center btn-primary'
                                                            href='{{ file_url($data->pdfURL) }}'><i
                                                                class='fa fa-eye'></i></a></td>
                                                @elseif(strtoupper($data->status) == 'PAYMENT SUCCESS')
                                                <td>
                                                    <a target='_blank' class='p-3 btn text-center btn-primary' onclick="RehitPdf({{$data->TraceID}})">
                                                        <i class='fa fa-refresh'></i> Rehit
                                                    </a>
                                                </td>
                                                @else
                                                    <td>NA</td>
                                                @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    @if (request()->type == 'rcNumber' || request()->type == 'engineNo' || request()->type == 'chassisNo')
                        <p style="font-size: 1rem;" class="card p-3 text-danger">
                            No Records or search for records
                        </p>
                    @endif
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    @if (!empty($traceIdDetails) && count($traceIdDetails) > 0)
        <script>
            $(document).ready(function() {
                $('#trace-enquiry-table').DataTable();
            });
        </script>
    @endif

    @if (!empty($data->encrypted_journey_id) || !empty(request()->userInput))
        <script>
            $(document).ready(function() {
                $(document).on('click', '.fa.fa-copy', function(e) {
                    const elem = document.createElement('textarea');
                    elem.innerHTML = e.target.getAttribute('data-copy');
                    document.body.appendChild(elem).select();
                    document.execCommand('copy')
                    document.body.removeChild(elem);
                    alert('Text copied...!')
                });
            });

            function RehitPdf(traceID) {
                let formData = new FormData();
                formData.append('enquiryId',traceID)
                rehitPdfApi(formData).then((res) => {
                    let data = res.response;
                    if(data.status == true) {
                        if (data.data.policy_number) {
                            let check = confirm(`Policy Number: ${data.data.policy_number}`)
                            if (data.data.pdf_link && check) {
                                window.open(data.data.pdf_link, '_blank')
                                location.reload();
                            }
                            
                        } else {
                            alert('Something went wrong while generating pdf.')
                        }
                        
                    } else if(data.msg || data.message){
                        alert(data.message ?? data.msg);
                    } else {
                        alert('Network Error')
                    }
                })
            }



            async function rehitPdfApi(data)
            {
                const response = await fetch('/api/generatePdf', {
                    method: 'post',
                    headers: {
                        "Accept": "application/json"
                    },
                    body: data
                })
                response.response = await response.json();
                return response;
            }
        </script>
    @endif
@endpush
