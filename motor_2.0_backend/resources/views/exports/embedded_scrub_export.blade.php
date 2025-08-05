<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <table>
        @foreach ($data as $key => $value)
            @if (isset($value['additional_data']['is_header']) && $value['additional_data']['is_header'])
                <tr>
                    @foreach ($value['content'] as $key1 => $item)
                        <th colspan="{{ $key1 == 'Ongrid Failure Data' ? 2 : 7 }}" style="text-align: center; font-weight:bold;">{{ $key1 }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($value['content'] as $key2 => $item)
                        @foreach ($item as $itm)
                            <th style="text-align: center; font-weight:bold;">{{ $itm }}</th>
                        @endforeach
                    @endforeach
                </tr>
            @else
            <tr>
                @foreach ($value['content'] as $key2 => $item)
                    @if ( ! empty($item))
                        @foreach ($item as $itm)
                            <td>{{ $itm }}</td>
                        @endforeach
                    @else
                        @for ($i=0; $i<7; $i++)
                            <td></td>
                        @endfor
                    @endif
                @endforeach
            </tr>
            @endif
        @endforeach
    </table>
</body>

</html>