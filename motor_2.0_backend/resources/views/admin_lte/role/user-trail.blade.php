@extends('admin_lte.layout.app', ['activePage' => 'User Trail', 'titlePage' => __('User Trail')])
<style>
.dropdown-toggle{
    border: 1px solid #ced4da !important;
}
.table td {
    max-width: 200px; 
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.table th {
    white-space: nowrap;
}

.main-footer {
  position: fixed;
  bottom: 0;
  width: 83%;
  background-color: red;
  color: white;
}
</style>

@section('content')
    <div class="container">
        <div id="errorMessage" class="alert alert-danger mt-3" style="display:none;"></div>

        <div class="row">

            <div class="col-md-4">
                <label for="user_id">Select User</label>
                <select id="user_id" class="form-control selectpicker" multiple data-live-search="true" required>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" class="form-control" required />
            </div>

            <div class="col-md-3">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" class="form-control" required />
            </div>

            <div class="col-md-2">
                <button id="filterBtn" class="btn btn-primary filterbtn" style="margin-top: 25px;">Filter</button>
            </div>
            <div class="col-md-2">
                <button id="downloadExcel" class="btn btn-success" style="margin-top: 30px;">Download Excel</button>
            </div>
        </div>

        <!-- Display table -->
        <div class="mt-4">
            <table class="table table-bordered" id="trailsTable">
                <thead>
                    <tr>
                        <th style="width: 20px;">#</th>
                        <th style="width: 220px;">URL</th>
                        <th style="width: 330px;">Parameters</th>
                        <th style="width: 20px;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be inserted here by AJAX -->
                </tbody>
            </table>
        </div>

    </div>
@endsection

@section('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            let today = new Date().toISOString().split('T')[0];
            $('#start_date').val(today);
            $('#end_date').val(today);

            // Initialize the selectpicker
            $('.selectpicker').selectpicker();
            // Initialize DataTable only once
            let table = $('#trailsTable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "scrollX": false,
            });

            $('#filterBtn').click(function() {
                $('#errorMessage').hide().text('');
                $('#trailsTable tbody').empty();

                let userIds = $('#user_id').val();
                let startDate = $('#start_date').val();
                let endDate = $('#end_date').val();
                const getTrailUrl = "{{ route('admin.user.trails.filter') }}";

                // Validate on client side
                if (!userIds || !startDate || !endDate) {
                    $('#errorMessage').show().text('All fields are required!');
                    return;
                }

                if (new Date(startDate) > new Date(endDate)) {
                    $('#errorMessage').show().text('End date must be equal to or after start date!');
                    return;
                }
                $('#filterBtn').prop('disabled', true);

                // AJAX request
                $.ajax({
                    url: getTrailUrl,
                    method: 'POST',
                    data: {
                        user_ids: userIds,
                        start_date: startDate,
                        end_date: endDate,
                         _token: "{{ csrf_token() }}"

                    },
                    success: function(response) {
                        // Clear previous data
                        table.clear();

                        if (response.status && response.data.length > 0) {
                            let counter = 1;
                            response.data.forEach(function(trail) {
                                let timestamp = new Date(trail.created_at);
                                let formattedDate = timestamp.toLocaleDateString(
                                    'en-CA');
                                let formattedTime = timestamp.toLocaleTimeString(
                                    'en-GB');
                                let escapedParameters = trail.parameters ? trail.parameters.replace(/"/g, '&quot;').replace(/'/g, '&#39;') : '';

                                // Add data to DataTable
                                table.row.add([
                                    counter++,
                                    `<span data-toggle="tooltip" title="${trail.url}">${trail.url}</span>`,
                                    `<span data-toggle="tooltip" title="${escapedParameters}">${escapedParameters}</span>`,
                                    // trail.parameters,
                                    `${formattedDate} ${formattedTime}`
                                ]);
                            });
                            table.draw();
                           $('#filterBtn').prop('disabled', false);
                            // $('[data-toggle="tooltip"]').tooltip();
                        } else {
                            table.row.add(['No data found', '', '', '']).draw();
                        }
                        // $('#filterBtn').prop('disabled', false);
                    },
                    error: function(xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#errorMessage').show().text(xhr.responseJSON.message);
                        } else {
                            $('#errorMessage').show().text(
                                'An error occurred while fetching data!');
                        }
                    }
                });
            });

            $('#downloadExcel').click(function() {
                let userIds = $('#user_id').val();
                let startDate = $('#start_date').val();
                let endDate = $('#end_date').val();

                window.location.href = "{{ route('admin.user.trails.export') }}?user_ids=" + userIds.join(',') +
                    "&start_date=" + startDate + "&end_date=" + endDate;
            });
        });
    </script>
@endsection
