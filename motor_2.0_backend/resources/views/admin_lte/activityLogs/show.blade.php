{{--@extends('layout.app')
@section('content')--}}
<style>
    .content {
        margin: 20px;
    }

    h4 {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 20px;
        text-align: center;
    }

    .table-responsive {
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        padding: 20px;
        background-color: #fff;
    }

.table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: auto;
    word-wrap: break-word;
}

    th, td {
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }

    th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #333;
    }

    td {
        vertical-align: middle;
        word-break: break-all;
    }

    a {
        color: #007bff;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    .sub-table {
        margin-top: 10px;
        width: 100%;
        border-collapse: collapse;
        word-break: break-word;
    }

    .sub-table th, .sub-table td {
        padding: 8px;
        border: 1px solid #ccc;
        background-color: #f9f9f9;
    }
</style>

<div class="content">
    <div class="table-responsive">
        <h4>Operation Performed: {{ $record['operation'] ?? '' }}</h4>
        <table class="table table-striped">
            <thead>
                @foreach ($record as $itemKey => $itemValue)
                    <tr>
                        <th>{{ ucwords(str_replace('_', ' ', $itemKey)) }}</th>
                        @if ($itemKey === 'trace_url')
                            <td><a target="_blank" title="{{$itemValue}}" href="{{ $itemValue }}">Link</a></td>
                        @elseif (in_array($itemKey, ['old_data', 'new_data']) && !empty($itemValue))
                            <td>
                                @php
                                    $data = json_decode($itemValue, true);
                                @endphp
                                @if(is_array($data) && !empty($data))
                                    <table class="sub-table">
                                        <tbody>
                                            @foreach($data as $subKey => $subValue)
                                                <tr>
                                                    <th>{{ $subKey }}</th>
                                                    <td>{{ $subValue }}</td>
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
</div>

{{--@endsection

@push('scripts')
<script>

</script>
@endpush--}}
