@extends('admin_lte.layout.app', ['activePage' => 'onepay-log', 'titlePage' => __('Onepay Logs')])
@section('content')
<div class="card">
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
            <!-- @csrf -->
            <div class="row mb-3">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label class="active" for="enquiryId">Enquiry Id</label>
                        <input class="form-control" id="enquiryId" name="enquiryId" type="text"
                            value="{{ old('enquiryId', request()->enquiryId ?? null) }}"
                            placeholder="Enquiry Id" required aria-required="true">
                    </div>

                </div>

                <div class="col-sm-3 text-right">
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit" style="margin-top: 30px;"><i
                                class="fa fa-search"></i> Search</button>
                    </div>
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
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th>Action</th>
                        <th class="text-right" scope="col">Enquiry ID</th>
                        <th class="text-right" scope="col">Order ID</th>
                        <th class="text-right" scope="col">Status</th>
                        <th class="text-right" scope="col">Created At</th>
                        <th class="text-right" scope="col">Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td scope="row">{{ $loop->iteration }}</td>
                            <td class="text-right">
                                <span class="btn btn-sm btn-info view" target="_blank" data-bs-toggle="modal" data-bs-target="#exampleModal"
                                    data="{{json_encode($log['data'])}}"
                                    target="_blank"><i
                                        class="fa fa-eye"></i></span>
                            </td>
                            <td>{{ customEncrypt($log['data']->user_product_journey_id) }}</td>
                            <td>{{ $log['data']->order_id }}</td>
                            <td>{{ $log['data']->status }}</td>
                            <td>{{ $log['data']->created_at }}</td>
                            <td>{{ $log['data']->updated_at }}</td>
                            {{--   <td>{{ $log['request'] ? substr($log['request'], 0, 50) . '...' : 'NA'}}</td>
                                <td>{{ $log['response'] ? substr($log['response'], 0, 50) . '...' : 'NA' }}</td> --}}
                            
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<p>No Records or search for records</p>
@endif


{{-- Modal --}}
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button class="btn btn-info me-1 btn-sm float-end download" id="download" type="button"><i
                class="fa fa-arrow-circle-down"></i> Download</button>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">
                        {{-- <h2>Response</h2> --}}
                        <div style="word-wrap: break-word;" id="showdata"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>

        </div>
    </div>
</div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            $(".table").DataTable();
            $(".datepickers").datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd",
            });
        });

        function copToClipboard(modal) {
            var range = document.createRange();
            range.selectNode(document.getElementById(modal));
            window.getSelection().removeAllRanges(); // clear current selection
            window.getSelection().addRange(range); // to select text
            document.execCommand("copy");
            window.getSelection().removeAllRanges(); // to deselect
        }

        function titleCase (s) {
            return s.replace(/^_*(.)|_+(.)/g, (s, c, d) => c ? c.toUpperCase() : ' ' + d.toUpperCase());
        }

        // Start file download.
        function onClickDownload(filename, headers, request, response, url) {
            let text = `URL :
                    ${url}

                    Headers :
                    ${headers}

                    Request :
                    ${request}

                    Response :
                    ${response}`;

            var filename = "request_response " + filename + ".txt";
            var element = document.createElement("a");
            element.setAttribute(
                "href",
                "data:text/plain;charset=utf-8," + encodeURIComponent(text)
            );
            element.setAttribute("download", filename);

            element.style.display = "none";
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }

        $(document).on("click", ".view", function () {
            
            var text = $(this).attr("data");

            data = JSON.parse(text);

            var new_string = "";

            console.log(data);
            Object.keys(data).forEach((key) => {
                const fruits = [
                    "id",
                    "quote_id",
                    "user_proposal_id",
                    "ic_id",
                    "customer_id",
                    "proposal_no",
                    "active",
                    "lead_source",
                    "xml_data",
                    "journey_id",
                ];
                if (!fruits.includes(key)) {
                    console.log(key, data[key]);
                    new_string = new_string + "<br>";
                    new_string =
                        new_string +
                        "<b>" +
                            titleCase(key == "user_product_journey_id" ? "Enquiry Id" : key) +
                        "</b>";
                    new_string =
                        new_string +
                        "<br/>" +
                        (key == "user_product_journey_id" ? data["journey_id"] : data[key]) +
                        "<br/>";
                }
            });

            $("#showdata").html(new_string);
            $("#download").attr("data", text);
        });

        $(".download").click(function () {
            


            var text = $(this).attr("data");

            data = JSON.parse(text);

            var new_string = "";

            console.log(data);
            Object.keys(data).forEach((key) => {
                const fruits = [
                    "id",
                    "quote_id",
                    "user_proposal_id",
                    "ic_id",
                    "customer_id",
                    "proposal_no",
                    "active",
                    "lead_source",
                    "xml_data",
                    "journey_id",
                ];
                if (!fruits.includes(key)) {
                    console.log(key, data[key]);
                    new_string = new_string + "\n";
                    new_string =
                        new_string +
                        "" +
                        titleCase(key == "user_product_journey_id" ? "Enquiry Id" : key) +
                        "";
                    new_string =
                        new_string +
                        "\n" +
                        (key == "user_product_journey_id" ? data["journey_id"] : data[key]) +
                        "\n";
                }
            });
            // Message :
            // ` + data.msg + `
            var filename = "onepay_" + data.journey_id + "_" + data.order_id + ".txt";
            var element = document.createElement("a");
            element.setAttribute(
                "href",
                "data:text/plain;charset=utf-8," + encodeURIComponent(new_string)
            );
            element.setAttribute("download", filename);

            element.style.display = "none";
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        });


    </script>
@endsection
