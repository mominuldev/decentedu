@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Position</th>
            <th>Name</th>
            <th>Section</th>
            <th>Total Obtained</th>
            <th>GPA</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td class="text-center">{{ $row['position'] ?? '-' }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['section'] ?? '-' }}</td>
                <td class="text-center">{{ $row['total_obtained'] }}</td>
                <td class="text-center">{{ $row['gpa'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
@endsection
