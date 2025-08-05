@extends('layout.app')
@section('content')
<style>
    body {
       width: 100%;
    }
    table {
        width: 100%;
    }
    .main-panel {
        width: 100% !important;
    }
    .page-body-wrapper {
        padding-top: 0 !important;
    }
    thead, tbody, tfoot, tr, td, th {
        border-width: 1px !important;
    }
</style>
<div class="content-wrapper">
    <div class="row table-responsive">
        <h4>Operation Performed : {{ $record['operation'] ?? '' }}</h4>
        <table class="table table-striped">
            <thead>
                @foreach ($record as $itemKey => $itemValue)
                    <tr>
                        <th>{{ ucwords(str_replace('_', ' ', $itemKey)) }}</th>
                        @if ($itemKey === 'trace_url')
                            <td><a  target="blank" title = {{$itemValue}} href="{{ $itemValue }}">Link</a></td>
                        @elseif (in_array($itemKey, ['old_data', 'new_data']) && !empty($itemValue))
                            <td>
                                @php
                                    $data = json_decode($itemValue, true);
                                @endphp
                                @if(is_array($data) && !empty($data))
                                    <table border="1" class="table table-responsive">
                                        <tbody>
                                            @foreach($data as $subKey => $subValue)
                                                <tr>
                                                    <th>{{ $subKey }}</th>
                                                    <td> {{ $subValue }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    {{ $itemValue }}    
                                @endif
                            </td>
                        @else
                            <td>{{ $itemValue }}</td>
                        @endif
                    </tr>
                @endforeach
            </thead>
        </table>
    </div>
@endsection


@push('scripts')
<script>

</script>
@endpush
