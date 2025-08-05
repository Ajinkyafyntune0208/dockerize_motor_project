@extends('admin_lte.layout.app', ['activePage' => 'rto-master', 'titlePage' => __('Sync RTO')])
@section('content')
<a href="{{ route('admin.rto-master.index') }}" class="btn btn-dark mb-4"><i class="fa fa-solid fa-arrow-left"></i></i></a>
    <div class="content">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card m-3 p-3">
                {{-- <div class="card-body"> --}}
                    <div class="card-header">
                        {{-- <h3 class="card-title">Sync RTO</h3> --}}
                        <button class="btn btn-primary float-right" id="sync-all-rto" onclick="syncAllRto(this)" type="button">Sync All RTO</button>
                    </div>
                    <div class="row m-1 p-1">
                        
                        <div id="fetch-alert-status" class="col-sm-8 alert" style="display: none;"></div>
                        <div class="col-sm-4 button text-center m-3 p-3" style="display: none;">
                            
                        </div>
                    </div>
                    <table id="fetch-all-rto" class="table"></table>
                {{-- </div> --}}
            </div>
        </div>
    </div>
    <!-- Modal -->
@endsection('content')

@section('scripts')
<script>
    //start loader while loading page
    const loader = document.querySelector('.loader');
    const loaderLayer = document.querySelector('.loader-layer');
    const loaderText = document.querySelector('.loader-text');

    if (loader && loaderLayer) {
        loader.classList.remove('d-none');
        loaderLayer.classList.remove('d-none');
    }

    async function prepareDataTable() {
        let data = [];
        await $.ajax({
            type: "GET",
            url: "{{ url('api/syncRto/all') }}",
            data: {
                "_token": "{{ csrf_token() }}",
            },
            success: function(response) {
                if (loader && loaderLayer) {
                    loader.classList.add('d-none');
                    loaderLayer.classList.add('d-none');
                }
                if (loaderText) {
                    loaderText.innerHTML = 'Syncing ...';
                }

                if (response.status === true) {
                    data = response.data;
                    $('#fetch-alert-status').removeClass('alert-info alert-danger').addClass('alert-success')
                        .text('Data Fetched Successfully!!').show();
                    $('#sync-all-rto').parent().show();
                } else if (response.status === false) {
                    $('#fetch-alert-status').removeClass('alert-info alert-success').addClass('alert-danger')
                        .text(response.msg).show();
                    $('#sync-all-rto').parent().hide();
                }
            }
        });

        if (!Array.isArray(data)) {
            console.error("Data is not an array", data);
            return;
        }

        let table = $('#fetch-all-rto');
        table.html(`
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>RTO Name</th>
                    <th>Status</th>
                    <th>Last Sync At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody></tbody>`);

        let rows = '';
        data.forEach((value, index) => {
            let srno = index + 1;
            let isLastTimeAvailable = value.lastSync ? true : false;
            let infoIcon = "mdi mdi-information-outline";
            let logo = isLastTimeAvailable ? 'mdi-database-refresh' : 'mdi-database-plus';
            let button = isLastTimeAvailable ? 'Re-Sync' : 'Sync';

            rows += `
                <tr>
                    <td>${srno}</td>
                    <td>${value.name}</td>
                    <td id="${value.name}">${value.status}</td>
                    <td title="${value.comment || "Never"}">
                        ${isLastTimeAvailable ? value.lastSync : '-'}
                        <span class="${isLastTimeAvailable ? infoIcon : ''}"></span>
                    </td>
                    <td>
                        <a href="#" onclick='syncSingleRto(event, "${value.name}")'>
                            <button type='button' id='sync_button_${value.name}' class='btn btn-warning'>
                                ${button}<span class='mdi ${logo}'></span>
                            </button>
                        </a>
                    </td>
                </tr>`;
        });

        table.find('tbody').append(rows);

        setTimeout(() => {
            $('.table').DataTable();
        }, 2000);
    }

    function syncSingleRto(event, id) {
        if (loader && loaderLayer) {
            loader.classList.remove('d-none');
            loaderLayer.classList.remove('d-none');
        }
        if (loaderText) {
            loaderText.innerHTML = 'Syncing ...';
        }

        let singleSyncUrl = "{{ url('api/syncRto') }}" + "/" + id;
        $.ajax({
            type: "GET",
            url: singleSyncUrl,
            data: {
                "_token": "{{ csrf_token() }}",
            },
            success: function(response) {
                if (loader && loaderLayer) {
                    loader.classList.add('d-none');
                    loaderLayer.classList.add('d-none');
                }
                if (response.status === true) {
                    $('#fetch-alert-status').removeClass('alert-info alert-danger').addClass('alert-success')
                        .text(response.msg).show();
                    $('#sync_button_' + id).html("Re-Sync<span class='mdi mdi-database-refresh'></span>").show();
                    $('#' + id).text("Synced").css({
                        "color": "green",
                        "font-weight": "bold"
                    }).show();
                } else if (response.status === false) {
                    $('#fetch-alert-status').removeClass('alert-info alert-success').addClass('alert-danger')
                        .text(response.msg).show();
                }
            }
        });
    }

    async function syncAllRto(e) {
        if (loader && loaderLayer) {
            loader.classList.remove('d-none');
            loaderLayer.classList.remove('d-none');
        }

        let data = [];
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
                    $('#fetch-alert-status').removeClass('alert-info alert-success').addClass('alert-danger')
                        .text(response.msg).show();
                }
            }
        });

        if (!Array.isArray(data)) {
            console.error("Data is not an array", data);
            return;
        }

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
                        $('#fetch-alert-status').removeClass('alert-info alert-danger').addClass('alert-success')
                            .text('Please wait, MMVs are being synced, ' + successCount + ' out of ' + totalConut + ' synced successfully.').show();
                        $('#sync_button_' + id).html("Re-Sync<span class='mdi mdi-database-refresh'></span>").show();
                        $('#' + id).text("Synced").css({
                            "color": "green",
                            "font-weight": "bold"
                        }).show();

                        if (loaderText) {
                            loaderText.innerHTML = Math.floor((successCount / totalConut) * 100) + '%';
                        }
                    } else if (response.status === false) {
                        $('#fetch-alert-status').removeClass('alert-info alert-success').addClass('alert-danger')
                            .text(response.msg).show();
                    }

                    if (index >= (totalConut - 1)) {
                        if (loader && loaderLayer) {
                            loader.classList.add('d-none');
                            loaderLayer.classList.add('d-none');
                        }
                        if (loaderText) {
                            loaderText.innerHTML = 'Syncing ...';
                        }
                    }
                }
            });
        });
    }

    $(document).ready(function() {
        $('#fetch-alert-status').addClass('alert-info').text("Fetching all available RTO.").show();
        console.log('fetching data');
        setTimeout(prepareDataTable, 5000);
    });

</script>
@endsection('scripts')
