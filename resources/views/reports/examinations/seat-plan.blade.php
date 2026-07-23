@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Roll</th>
            <th>Name</th>
            <th>Room</th>
            <th>Seat No</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['roll'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['room'] }}</td>
                <td class="text-center">{{ $row['seat_no'] }}</td>
            </tr>
        @endforeach
    </table>
@endsection
