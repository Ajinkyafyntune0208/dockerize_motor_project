@extends('layout.app', ['activePage' => 'fetch-all-masters', 'titlePage' => __('Fetch All Masters')])

@section('content')
<div class="content-wrapper">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div id="fetch-alert-status" class="col-sm-8 alert" style="display: none;"></div>
                    <div class="col-sm-4 button text-center" style="display: none;"><button class="btn btn-primary" id="sync-all-masters" onclick="syncAllMasters(this)" type="button">Sync All Masters</button></div>
                </div>
                <table id="fetch-all-masters" class="table">
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

    function fetchAllMasters() {
        $.ajax({
            type: "post",
            url: "{{ route('mdm.get.all.masters') }}",
            data: {
                "_token": "{{ csrf_token() }}",
            },
            success: function(response) {

                //hide the loader after getting response
                document.querySelector('.loader').classList.add('d-none')
                document.querySelector('.loader-layer').classList.add('d-none')
                document.querySelector('.loader-text').innerHTML = 'Syncing ...'

                if (response.status === true) {
                    $('#fetch-alert-status').removeClass('alert-info alert-danger');
                    $('#fetch-alert-status').addClass('alert-success');
                    $('#fetch-alert-status').text('Data Fetched Successfully!!').show();
                    prepareDataTable(response.data);
                    $('#sync-all-masters').parent().show();
                } else if (response.status === false) {
                    $('#fetch-alert-status').removeClass('alert-info alert-success');
                    $('#fetch-alert-status').addClass('alert-danger');
                    $('#fetch-alert-status').text(response.message || response.msg).show();
                    $('#sync-all-masters').parent().hide();
                }
            }
        });
    }
    function prepareDataTable(data) {
        let table = $('#fetch-all-masters');
        table.append(`
        <thead>
            <tr>
                <th>Master ID</th>
                <th>Over-Write Master ID</th>
                <th>Master Name</th>
                <th>Master Type</th>
                <th>Total Rows</th>
                <th>Last Updated Time</th>
                <th>Action</th>
            </tr>
        </thead><tbody>`);
        var row = '';
        data.forEach(function(value, index) {
            let srno = index + 1;
            row += "<tr>";
            row += "<td>" + value.id + "</td>";
            row += "<td>" + (value.overwrite_master_id || '-') + "</td>";
            row += "<td>" + value.master_name + "</td>";
            row += "<td>" + value.type + "</td>";
            row += "<td>" + value.rows_count + "</td>";
            let infoIcon = "mdi mdi-information-outline";
            let isLastTimeAvailable = value.last_updated_time ? true : false;
            row += "<td title='" + (value.comment || "Never") + "'>" + (isLastTimeAvailable ? value.last_updated_time : '-')  + "<span class='"+ (isLastTimeAvailable ? infoIcon : '') +"'></span></td>";
            let logo = value.table_exists ? 'mdi-database-refresh' : 'mdi-database-plus';
            let button = value.table_exists ? 'Sync' : 'Re-Sync';
            row += "<td><a href='#' onclick='syncSingleMaster(event, "+ value.id +")'><button type='button' id='sync_button_" + srno + "' class='btn btn-warning'>"+ button +"<span class='mdi " + logo + "'></span></button></a></td>";
            row += "</tr>";
        });
        row += '</tbody>';
        table.append(row);

        setTimeout(function () {
            $('.table').DataTable();
        }, 2000);
    }
    function syncSingleMaster(event, id) {
        
        // start the loader while syncing
        document.querySelector('.loader').classList.remove('d-none')
        document.querySelector('.loader-layer').classList.remove('d-none')

        let singleSyncUrl = "{{url('api/mdm/sync-single-master')}}" + "/" + id;
        var triggeredButton = $(event.target.id);
        $.ajax({
            type: "put",
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
                    $('#fetch-alert-status').text(response.message).show();
                } else if (response.status === false) {
                    $('#fetch-alert-status').removeClass('alert-info alert-success');
                    $('#fetch-alert-status').addClass('alert-danger');
                    $('#fetch-alert-status').text(response.message || response.msg).show();
                }
            }
        });
    }
    function syncAllMasters(e) {
        
        if(confirm("All masters fetched from MDM will be synced with this portal. Are you sure you want to proceed with the sync?")){
        let allSyncUrl = "{{url('api/mdm/sync-all-masters')}}";
        $.ajax({
            type: "put",
            url: allSyncUrl,
            data: {
                "_token": "{{ csrf_token() }}",
            },
            success: function(response) {
                if (response.status === true) {
                    $('#fetch-alert-status').removeClass('alert-info alert-danger');
                    $('#fetch-alert-status').addClass('alert-success');
                    $('#fetch-alert-status').text(response.message).show();
                    $('#sync-all-masters').parent().fadeOut('slow');
                } else if (response.status === false) {
                    $('#fetch-alert-status').removeClass('alert-info alert-success');
                    $('#fetch-alert-status').addClass('alert-danger');
                    $('#fetch-alert-status').text(response.message || response.msg).show();
                }
            }
        });
      }
    }
    $(document).ready(function() {
        $('#fetch-alert-status').addClass('alert-info');
        $('#fetch-alert-status').text("Fetching all available Masters from MDM.").show();
        setTimeout(fetchAllMasters(), 5000);
    });
</script>
@endpush
