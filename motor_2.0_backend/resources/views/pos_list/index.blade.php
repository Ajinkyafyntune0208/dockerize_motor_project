@extends('layout.app', ['activePage' => 'pos-data', 'titlePage' => __('POS List')]) 
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title" style="margin-bottom: -100px"> POS Agent List</h5>
                    </div>
                    @if (!empty($lists))
                        <div class="card-body">
                            <hr style="margin-bottom: 20px">
                            <div class="table-responsive">
                                <table class="table-striped table" id="table_detail">
                                    <thead>
                                        @if (!empty($lists[0]))
                                        @foreach ($lists->first()->getattributes() as $key => $col)
                                            <th scope="col">{{ $key }}</th>
                                        @endforeach
                                        @else
                                            <p>No data available in table</p>
                                        @endif
                                    </thead>
                                    <tbody>
                                        @foreach ($lists as $key => $val)
                                            <tr>
                                                @foreach ($val->getattributes() as $colKey => $col)
                                                    <td loading="lazy" scope="row">{{ $col }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="row">
                                <div class="col-lg-11">
                                    <div class="pagination-container mt-3">
                                        <nav aria-label="Page navigation example">
                                            <ul class="pagination">
                                                @php
                                                    $page_counts = ceil($list_counts / 10);
                                                    $currentPage = $lists->currentPage();
                                                    $lastPage = $lists->lastPage();
                                                @endphp
                                                <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                                                    <a class="page-link" href="{{ $lists->previousPageUrl() }}">Previous</a>
                                                </li>                              
                                                @if ($page_counts <= 5)
                                                    @for ($i = 1; $i <= $page_counts; $i++)
                                                        <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                                                            <a class="page-link" href="{{ $lists->url($i) }}">{{ $i }}</a>
                                                        </li>
                                                    @endfor
                                                @else
                                                    @for ($i = 1; $i <= 2; $i++)
                                                        <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                                                            <a class="page-link" href="{{ $lists->url($i) }}">{{ $i }}</a>
                                                        </li>
                                                    @endfor
                                                    @if ($currentPage > 2)
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    @endif
                                                    @for ($i = max(3, $currentPage - 1); $i <= min($lastPage - 2, $currentPage + 1); $i++)
                                                        <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                                                            <a class="page-link" href="{{ $lists->url($i) }}">{{ $i }}</a>
                                                        </li>
                                                    @endfor
                                                    @if ($currentPage < $lastPage - 3)
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    @endif
                                                    @for ($i = $lastPage - 1; $i <= $lastPage; $i++)
                                                        <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                                                            <a class="page-link" href="{{ $lists->url($i) }}">{{ $i }}</a>
                                                        </li>
                                                    @endfor
                                                @endif
                                                <li class="page-item {{ $currentPage == $lastPage ? 'disabled' : '' }}">
                                                    <a class="page-link" href="{{ $lists->nextPageUrl() }}">Next</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log("DataTable");
            $('#table_detail').DataTable({
                dom: 'Blfrtip',  
                pageLength: 10,
                paging: false,
                lengthMenu: [ [10, 25, 50, 75, 100, -1], [10, 25, 50, 75, 100, "All"] ],
                buttons: [
                    {
                        extend: "excelHtml5",
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                    {
                        extend: "csvHtml5",
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                ]
            });
        });
    </script>
@endpush
