@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Student</th>
            @foreach ($data['subjects'] as $s)
                <th>{{ $s['name'] }}</th>
            @endforeach
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                @foreach ($data['subjects'] as $s)
                    <td class="text-center">{{ $row['marks'][$s['id']] ?? '-' }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endsection
