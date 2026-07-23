@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Name</th>
            <th>Section</th>
            <th>Total Obtained</th>
            <th>Failed Subjects</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['section'] ?? '-' }}</td>
                <td class="text-center">{{ $row['total_obtained'] }}</td>
                <td>{{ $row['failed_subjects']->join(', ') }}</td>
            </tr>
        @endforeach
    </table>
@endsection
