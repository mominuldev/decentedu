@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Roll</th>
            <th>Name</th>
            <th>Signature</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['roll'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>&nbsp;</td>
            </tr>
        @endforeach
    </table>
@endsection
