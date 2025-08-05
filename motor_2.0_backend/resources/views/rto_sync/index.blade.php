@extends('layout.app', ['activePage' => 'fetch-all-rto', 'titlePage' => __('Fetch All RTO')])

@section('content')
    <div class="content-wrapper">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div id="fetch-alert-status" class="col-sm-8 alert" style="display: none;"></div>
                        <div class="col-sm-4 button text-center" style="display: none;"><button class="btn btn-primary"
                                id="sync-all-rto" onclick="syncAllRto(this)" type="button">Sync All RTO</button></div>
                    </div>
                    <table id="fetch-all-rto" class="table">
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        //start loader while loading page
        document.querySelector('.loader').classList.remove('d-none')
        document.querySelector('.loader-layer').classList.remove('d-none')

        async function prepareDataTable() {
            let data;
            await $.ajax({
                type: "get",
                url: "{{ url('api/syncRto/all') }}",
                data: {
                    "_token": "{{ csrf_token() }}",
                },
                success: function(response) {
                    //hide the loader after getting response
                    document.querySelector('.loader').classList.add('d-none')
                    document.querySelector('.loader-layer').classList.add('d-none')
                    document.querySelector('.loader-text').innerHTML = 'Syncing ...'

                    data = response.data
                    if (response.status === true) {
                        $('#fetch-alert-status').removeClass('alert-info alert-danger');
                        $('#fetch-alert-status').addClass('alert-success');
                        $('#fetch-alert-status').text('Data Fetched Successfully!!').show();
                        $('#sync-all-rto').parent().show();
                        data = response.data;
                    } else if (response.status === false) {
                        $('#fetch-alert-status').removeClass('alert-info alert-success');
                        $('#fetch-alert-status').addClass('alert-danger');
                        $('#fetch-alert-status').text(response.msg).show();
                        $('#sync-all-rto').parent().hide();
                    }
                }
            });
            let table = $('#fetch-all-rto');
            table.append(`
        <thead>
            <tr>
                <th>Sr. No.</th>
                <th>RTO Name</th>
                <th>Status</th>
                <th>Last Sync At</th>
                <th>Action</th>
            </tr>
        </thead><tbody>`);
            var row = '';
            data.forEach(function(value, index) {
                let srno = index + 1;
                row += "<tr>";
                row += "<td>" + srno + "</td>";
                row += "<td>" + value.name + "</td>";
                row += "<td id='" + value.name + "'>" + value.status + "</td>";
                let isLastTimeAvailable = value.lastSync ? true : false;
                let infoIcon = "mdi mdi-information-outline";
                row += "<td title='" + (value.comment || "Never") + "'>" + (isLastTimeAvailable ? value.lastSync :
                        '-') +"<span class='" + (isLastTimeAvailable ? infoIcon : '') +
                    "'></span></td>";
                let logo = isLastTimeAvailable ? 'mdi-database-refresh' : 'mdi-database-plus';
                let button = isLastTimeAvailable ? 'Re-Sync' : 'Sync';
                row += `<td><a href='#' onclick='syncSingleRto(event,"` + value.name +
                    `")'><button type='button' id='sync_button_` + value.name + `' class='btn btn-warning'>` +
                    button +
                    `<span class='mdi ` + logo + `'></span></button></a></td>`;
                row += "</tr>";
            });
            row += '</tbody>';
            table.append(row);
            setTimeout(function() {
                $('.table').DataTable();
            }, 2000);
        }

        function syncSingleRto(event, id) {
            // start the loader while syncing
            document.querySelector('.loader').classList.remove('d-none')
            document.querySelector('.loader-layer').classList.remove('d-none')
            document.querySelector('.loader-text').innerHTML = 'Syncing ...'

            let singleSyncUrl = "{{ url('api/syncRto') }}" + "/" + id;
            var triggeredButton = $(event.target.id);
            $.ajax({
                type: "GET",
                url: singleSyncUrl,
                data: {
                    "_token": "{{ csrf_token() }}",
                },
                success: function(response) {
                    // stop the loader after sync
                    document.querySelector('.loader').classList.add('d-none')
                    document.querySelector('.loader-layer').classList.add('d-none')

                    if (response.status === true) {
                        $('#fetch-alert-status').removeClass('alert-info alert-danger');
                        $('#fetch-alert-status').addClass('alert-success');
                        $('#fetch-alert-status').text(response.msg).show();
                        $('#sync_button_' + id).html("Re-Sync<span class='mdi mdi-database-refresh'></span>")
                            .show();
                        $('#' + id).text("Synced").css({
                            "color": "green",
                            "font-weight": "bold"
                        }).show();
                    } else if (response.status === false) {
                        $('#fetch-alert-status').removeClass('alert-info alert-success');
                        $('#fetch-alert-status').addClass('alert-danger');
                        $('#fetch-alert-status').text(response.msg).show();
                    }
                }
            });
        }

        async function syncAllRto(e) {
            //start loader while loading page
            document.querySelector('.loader').classList.remove('d-none')
            document.querySelector('.loader-layer').classList.remove('d-none')
            let data;
            let successCount = 0;
            let totalConut = 0;
            await $.ajax({
                type: "GET",
                url: "{{ url('api/syncRto/all') }}",
                data: {
                    "_token": "{{ csrf_token() }}",
                },
                success: function(response) {
                    if (response.status === true) {
                        data = response.data;
                        $('#sync-all-rto').parent().fadeOut('slow');
                    } else if (response.status === false) {
                        $('#fetch-alert-status').removeClass('alert-info alert-success');
                        $('#fetch-alert-status').addClass('alert-danger');
                        $('#fetch-alert-status').text(response.msg).show();
                    }
                }
            });
            totalConut = data.length;
            data.forEach((rto, index) => {
                const id = rto.name;
                let singleSyncUrl = "{{ url('api/syncRto') }}" + "/" + id;
                $.ajax({
                    type: "GET",
                    url: singleSyncUrl,
                    data: {
                        "_token": "{{ csrf_token() }}",
                    },
                    success: function(response) {
                        if (response.status === true) {
                            successCount++;
                            $('#fetch-alert-status').removeClass('alert-info alert-danger');
                            $('#fetch-alert-status').addClass('alert-success');
                            $('#fetch-alert-status').text('Please wait, MMVs are being synced, ' +
                                successCount +
                                ' out of ' + totalConut + ' synced successfully.').show();
                            $('#sync_button_' + id).html(
                                "Re-Sync<span class='mdi mdi-database-refresh'></span>").show();
                            $('#' + id).text("Synced").css({
                                "color": "green",
                                "font-weight": "bold"
                            }).show();

                            document.querySelector('.loader-text').innerHTML =  Math.floor((successCount / totalConut) * 100) + '%';

                        } else if (response.status === false) {
                            $('#fetch-alert-status').removeClass('alert-info alert-success');
                            $('#fetch-alert-status').addClass('alert-danger');
                            $('#fetch-alert-status').text(response.msg).show();
                        }

                        if (index >= (totalConut - 1)) {
                            document.querySelector('.loader').classList.add('d-none')
                            document.querySelector('.loader-layer').classList.add('d-none')
                            document.querySelector('.loader-text').innerHTML = 'Syncing ...'
                        }
                    }
                });
            });
        }
        $(document).ready(function() {
            $('#fetch-alert-status').addClass('alert-info');
            $('#fetch-alert-status').text("Fetching all available RTO.").show();
            setTimeout(prepareDataTable(), 5000);
        });
    </script>
@endpush
