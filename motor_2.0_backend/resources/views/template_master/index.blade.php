@extends('layout.app', ['activePage' => 'admin', 'titlePage' => __('Template Master')])
@section('content')
    <style>
        #rto_zone,
        #rto_status,
        #rto_state {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 911px;
                margin: 34px auto;
                word-wrap: break-word;
            }
        }
    </style>

    <!-- partial -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Template List
                            @if (auth()->user()->can('user.create'))
                                <a href="{{ route('admin.template.create') }}" class="btn btn-primary btn-sm float-end">
                                    <i class="fa fa-plus"></i> Add Template
                                </a>
                            @endif
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-striped" id="response_log">
                                <thead>
                                    <tr>
                                        <th scope="col">Sr. No.</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Created At</th>
                                        <th scope="col" class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($templates as $key => $template)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $template->title }}</td>
                                            <td>{{ $template->communication_type }}</td>
                                            <td>{{ $template->status }}</td>
                                            <td>{{ $template->created_at }}</td>
                                            <td>
                                                <form action="{{ route('admin.template.destroy', $template) }}"
                                                    method="post" onsubmit="return confirm('Are you sure..?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="btn-group">
                                                        @if (auth()->user()->can('user.edit'))
                                                            <a href="{{ route('admin.template.edit', $template->template_id) }}"
                                                                class="btn btn-sm btn-outline-success show-template">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                        @endif
                                                        @if (auth()->user()->can('user.delete'))
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#response_log').DataTable({
                initComplete: function() {
                    var notApplyFilterOnColumn = [0, 5];
                    var inputFilterOnColumn = [];
                    var showFilterBox = 'afterHeading'; // beforeHeading, afterHeading

                    $('.gtp-dt-filter-row').remove();
                    var theadSecondRow = '<tr class="gtp-dt-filter-row">';
                    $(this).find('thead tr th').each(function(index) {
                        theadSecondRow += '<td class="gtp-dt-select-filter-' + index +
                        '"></td>';
                    });
                    theadSecondRow += '</tr>';

                    if (showFilterBox === 'beforeHeading') {
                        $(this).find('thead').prepend(theadSecondRow);
                    } else if (showFilterBox === 'afterHeading') {
                        $(theadSecondRow).insertAfter($(this).find('thead tr'));
                    }

                    this.api().columns().every(function(index) {
                        var column = this;
                        if (index === 4) { // Date column
                            var minDateInput = $(
                                    '<input type="text" id="minDate" class="datepicker" placeholder="Search by Date">'
                                    )
                                .datepicker({
                                    dateFormat: "yy-mm-dd"
                                });
                            $('td.gtp-dt-select-filter-' + index).html(minDateInput);

                            $('.datepicker').on('change', function() {
                                table.draw();
                            });

                            $.fn.dataTable.ext.search.push(
                                function(settings, data, dataIndex) {
                                    var min = $('#minDate').datepicker("getDate");
                                    var createdAt = new Date(data[
                                    4]); // Use index for 'Created At' column

                                    if (min === null) {
                                        return true;
                                    } else {
                                        if (min.getFullYear() == createdAt.getFullYear() &&
                                            min.getMonth() == createdAt.getMonth() && min
                                            .getDate() == createdAt.getDate()) {
                                            if (min <= createdAt) {
                                                return true;
                                            }
                                        }
                                    }
                                }
                            );
                        } else if (inputFilterOnColumn.indexOf(index) >= 0 &&
                            notApplyFilterOnColumn.indexOf(index) < 0) {
                            $('td.gtp-dt-select-filter-' + index).html(
                                '<input type="text" class="gtp-dt-input-filter">');
                            $('td input.gtp-dt-input-filter').on('keyup change clear',
                            function() {
                                if (column.search() !== this.value) {
                                    column.search(this.value).draw();
                                }
                            });
                        } else if (notApplyFilterOnColumn.indexOf(index) < 0) {
                            var select = $(
                                    '<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker" data-live-search="true"><option value="">Select</option></select>'
                                    )
                                .on('change', function() {
                                    var val = $.fn.dataTable.util.escapeRegex($(this)
                                .val());
                                    column.search(val ? '^' + val + '$' : '', true, false)
                                        .draw();
                                });

                            $('td.gtp-dt-select-filter-' + index).html(select);
                            column.data().unique().sort().each(function(d, j) {
                                select.append('<option value="' + d + '">' + d +
                                    '</option>');
                            });
                        }
                    });
                }
            });

            // Bootstrap selectpicker styling
            $('[name="response_log_length"]').attr({
                "data-style": "btn-sm btn-primary",
                "data-actions-box": "true",
                "class": "selectpicker w-100 px-3",
                "data-live-search": "true"
            });
            $('.selectpicker').selectpicker();

            // Hide success alert after 2 seconds
            setTimeout(() => {
                $('.alert-success').fadeOut('slow');
            }, 2000);
        });
    </script>
@endpush
